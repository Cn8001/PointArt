<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
namespace PointStart\ORM{
    require_once __DIR__ . '/../attributes/Query.php';

    abstract class Repository{
        /** Subclass must set: protected string $entityClass = SomeModel::class; */
        protected string $entityClass;

        // ── Built-in CRUD delegates ─────────────────────────────────────────────

        public function find(int|string $id): ?object{
            return $this->entityClass::find($id);
        }

        public function findAll(): array{
            return $this->entityClass::findAll();
        }

        public function save(object $entity): void{
            $entity->save();
        }

        public function delete(object $entity): void{
            $entity->delete();
        }

        public function deleteById(int|string $id): void{
            $entity = $this->entityClass::find($id);
            if($entity !== null) $entity->delete();
        }

        /**
         * Generates a concrete subclass for an abstract Repository that has
         * #[Query]-annotated abstract methods, then returns an instance.
         *
         * Usage:
         *   abstract class UserRepository extends Repository {
         *       protected string $entityClass = User::class;
         *
         *       #[Query("SELECT * FROM users WHERE name = ?")]
         *       abstract public function findByNameRaw(string $name): array;
         *   }
         *
         *   $repo = UserRepository::make();
         */
        public static function make(): static{
            $className    = static::class;
            $concreteName = $className . '__Impl';

            if(!class_exists($concreteName)){
                $ref     = new \ReflectionClass($className);
                $methods = '';
                foreach($ref->getMethods(\ReflectionMethod::IS_ABSTRACT) as $method){
                    $name       = $method->getName();
                    $returnType = self::resolveReturnType($method);
                    $attrs      = $method->getAttributes(\PointStart\Attributes\Query::class);
                    $isVoid     = $returnType === ': void';
                    $ret        = $isVoid ? '' : 'return ';
                    $body       = empty($attrs)
                        ? "{$ret}\$this->__call('$name', \$a);"   // dynamic finder
                        : "{$ret}\$this->runQuery('$name', \$a);"; // #[Query] SQL
                    $methods   .= "public function $name(...\$a)$returnType { $body }\n";
                }
                eval("class $concreteName extends $className { $methods }");
            }

            return new $concreteName();
        }

        private static function resolveReturnType(\ReflectionMethod $method): string{
            if(!$method->hasReturnType()) return '';
            $rt = $method->getReturnType();
            if($rt instanceof \ReflectionNamedType){
                $nullable = $rt->allowsNull() && $rt->getName() !== 'mixed' ? '?' : '';
                return ': ' . $nullable . $rt->getName();
            }
            return ': ' . $rt; // union / intersection types
        }

        // ── Dynamic method dispatch ─────────────────────────────────────────────

        /**
         * Supports:
         *   findBy<Field>[And<Field>...][OrderBy<Field>]($args)
         *   findOneBy<Field>[And<Field>...]($args)
         *   countBy<Field>[And<Field>...]($args)
         *   existsBy<Field>[And<Field>...]($args)
         *   deleteBy<Field>[And<Field>...]($args)
         *
         * Operator suffixes: GreaterThan, LessThan, GreaterThanEqual,
         *   LessThanEqual, Not, Like, IsNull, IsNotNull
         */
        /**
         * Execute the #[Query] SQL on the calling method and return hydrated results.
         * Use inside a method annotated with #[Query]:
         *
         *   #[Query("SELECT * FROM users WHERE name = ?")]
         *   public function findByNameRaw(string $name): array {
         *       return $this->runQuery(__FUNCTION__, func_get_args());
         *   }
         */
        protected function runQuery(string $method, array $args): mixed{
            // Walk up the hierarchy — the #[Query] attr lives on the abstract declaration
            $attrs = [];
            $class = new \ReflectionClass($this);
            while($class){
                if($class->hasMethod($method)){
                    $attrs = $class->getMethod($method)->getAttributes(\PointStart\Attributes\Query::class);
                    if(!empty($attrs)) break;
                }
                $class = $class->getParentClass();
            }
            if(empty($attrs)){
                throw new \BadMethodCallException("Method $method has no #[Query] attribute");
            }
            $sql  = $attrs[0]->newInstance()->queryString;
            $pdo  = $this->entityClass::getPdo();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($args);

            $verb = strtoupper(strtok(ltrim($sql), " \t\n"));
            if($verb === 'SELECT'){
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                // COUNT query — return scalar
                if(stripos($sql, 'COUNT(') !== false){
                    return (int)($rows[0][array_key_first($rows[0])] ?? 0);
                }
                return array_map(fn($row) => $this->entityClass::map($row), $rows);
            }
            // DELETE / UPDATE / INSERT — return affected row count
            return $stmt->rowCount();
        }

        public function __call(string $name, array $args): mixed{
            // 1. Detect prefix (longest-first to avoid partial matches)
            $prefixes = ['findOneBy', 'findBy', 'countBy', 'existsBy', 'deleteBy'];
            $prefix   = null;
            $remainder = null;

            foreach($prefixes as $p){
                if(str_starts_with($name, $p)){
                    $prefix    = $p;
                    $remainder = substr($name, strlen($p));
                    break;
                }
            }
            if($prefix === null){
                throw new \BadMethodCallException("Method $name not found on " . static::class);
            }

            // 2. Extract OrderBy (only meaningful for findBy)
            $orderByCol = null;
            if(($pos = strpos($remainder, 'OrderBy')) !== false){
                $orderProp  = lcfirst(substr($remainder, $pos + 7));
                $remainder  = substr($remainder, 0, $pos);
                $orderByCol = $this->propToColumn($orderProp);
            }

            // 3. Parse condition segments — split on camelCase "And"
            $segments = preg_split('/(?<=[a-z])And(?=[A-Z])/', $remainder);

            $operators = [
                'IsNotNull'      => 'IS NOT NULL',
                'IsNull'         => 'IS NULL',
                'GreaterThanEqual' => '>=',
                'LessThanEqual'  => '<=',
                'GreaterThan'    => '>',
                'LessThan'       => '<',
                'Not'            => '!=',
                'Like'           => 'LIKE',
            ];

            $clauses = [];
            $params  = [];
            $argIdx  = 0;
            foreach($segments as $seg){
                $operator = '=';
                $propPart = $seg;
                foreach($operators as $suffix => $op){
                    if(str_ends_with($propPart, $suffix)){
                        $operator = $op;
                        $propPart = substr($propPart, 0, -strlen($suffix));
                        break;
                    }
                }
                $col = $this->propToColumn(lcfirst($propPart));
                if($operator === 'IS NULL' || $operator === 'IS NOT NULL'){
                    $clauses[] = "`$col` $operator";
                } else {
                    $clauses[] = "`$col` $operator ?";
                    $params[]  = $args[$argIdx++];
                }
            }

            // 4. Build and run the query
            $table   = $this->entityClass::getTableName();
            $pdo     = $this->entityClass::getPdo();
            $whereStr = implode(' AND ', $clauses);

            switch($prefix){
                case 'findBy':
                    $sql = "SELECT * FROM `$table` WHERE $whereStr";
                    if($orderByCol !== null) $sql .= " ORDER BY `$orderByCol`";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    return array_map(
                        fn($row) => $this->entityClass::map($row),
                        $stmt->fetchAll(\PDO::FETCH_ASSOC)
                    );

                case 'findOneBy':
                    $sql  = "SELECT * FROM `$table` WHERE $whereStr LIMIT 1";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $row  = $stmt->fetch(\PDO::FETCH_ASSOC);
                    return $row ? $this->entityClass::map($row) : null;

                case 'countBy':
                    $sql  = "SELECT COUNT(*) FROM `$table` WHERE $whereStr";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    return (int)$stmt->fetchColumn();

                case 'existsBy':
                    $sql  = "SELECT COUNT(*) FROM `$table` WHERE $whereStr";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    return (int)$stmt->fetchColumn() > 0;

                case 'deleteBy':
                    $sql  = "DELETE FROM `$table` WHERE $whereStr";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    return null;
            }
            return null;
        }

        /** Looks up a camelCase property name in the entity's column map */
        private function propToColumn(string $propName): string{
            $columns = $this->entityClass::getColumns(); // [propName => colName]
            if(isset($columns[$propName])){
                return $columns[$propName];
            }
            throw new \InvalidArgumentException(
                "Property '$propName' not found in " . $this->entityClass
            );
        }
    }
}
?>
