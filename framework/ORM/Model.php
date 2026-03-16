<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
namespace PointStart\ORM{
    use PDO;
    use ReflectionClass;
    use PointStart\Attributes\Column;
    use PointStart\Attributes\Entity;
    use PointStart\Attributes\Id;

    abstract class Model{
        public static ?PDO $pdo = null;

        private static function connect(): PDO{
            if(Model::$pdo !== null){
                return Model::$pdo;
            }
            $config = require __DIR__ . '/../../config.php';
            $db = $config['db'];
            $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset={$db['charset']}";
            Model::$pdo = new PDO($dsn, $db['username'], $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return Model::$pdo;
        }

        /// Returns the PDO connection — useful for Repository raw queries
        public static function getPdo(): PDO{
            return static::connect();
        }

        // Reads #[Entity] on the subclass and returns the table name
        public static function getTableName(): string{
            $ref = new ReflectionClass(static::class);
            $attrs = $ref->getAttributes(Entity::class);
            if(empty($attrs)){
                throw new \RuntimeException('No #[Entity] attribute on ' . static::class);
            }
            return $attrs[0]->newInstance()->tableName;
        }

        /**
         * Returns [propName => columnName] for every property annotated
         * with #[Column] or #[Id] (Id without Column defaults to prop name).
         */
        public static function getColumns(): array{
            $ref = new ReflectionClass(static::class);
            $columns = [];
            foreach($ref->getProperties() as $prop){
                $colAttrs = $prop->getAttributes(Column::class);
                if(!empty($colAttrs)){
                    $columns[$prop->getName()] = $colAttrs[0]->newInstance()->columnName;
                } elseif(!empty($prop->getAttributes(Id::class))){
                    $columns[$prop->getName()] = $prop->getName();
                }
            }
            return $columns;
        }

        // Returns the property name annotated with #[Id]
        public static function getPrimaryKey(): string{
            $ref = new ReflectionClass(static::class);
            foreach($ref->getProperties() as $prop){
                if(!empty($prop->getAttributes(Id::class))){
                    return $prop->getName();
                }
            }
            throw new \RuntimeException('No #[Id] property found on ' . static::class);
        }

        // Maps a raw DB row array onto a new instance of the calling subclass
        public static function map(array $row): static{
            $ref      = new ReflectionClass(static::class);
            $instance = $ref->newInstanceWithoutConstructor();
            $colToProp = array_flip(static::getColumns()); // [colName => propName]
            foreach($row as $colName => $value){
                $propName = $colToProp[$colName] ?? null;
                if($propName !== null && $ref->hasProperty($propName)){
                    $prop = $ref->getProperty($propName);
                    $prop->setAccessible(true);
                    $prop->setValue($instance, $value);
                }
            }
            return $instance;
        }

        // ── Static query methods ────────────────────────────────────────────────

        public static function find(int|string $id): ?static{
            $pdo   = static::connect();
            $table = static::getTableName();
            $pk    = static::getPrimaryKey();
            $pkCol = static::getColumns()[$pk];
            $stmt  = $pdo->prepare("SELECT * FROM `$table` WHERE `$pkCol` = ? LIMIT 1");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            return $row ? static::map($row) : null;
        }

        public static function findAll(): array{
            $pdo   = static::connect();
            $table = static::getTableName();
            $stmt  = $pdo->query("SELECT * FROM `$table`");
            return array_map(fn($row) => static::map($row), $stmt->fetchAll());
        }

        /**
         * @param array       $conditions  [columnName => value]
         * @param string|null $orderBy     Raw ORDER BY expression (e.g. "name ASC")
         * @param int|null    $limit
         */
        public static function findBy(array $conditions, ?string $orderBy = null, ?int $limit = null): array{
            $pdo    = static::connect();
            $table  = static::getTableName();
            $where  = [];
            $params = [];
            foreach($conditions as $col => $val){
                $where[]  = "`$col` = ?";
                $params[] = $val;
            }
            $sql = "SELECT * FROM `$table`";
            if(!empty($where)) $sql .= " WHERE " . implode(" AND ", $where);
            if($orderBy !== null) $sql .= " ORDER BY $orderBy";
            if($limit !== null)   $sql .= " LIMIT $limit";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return array_map(fn($row) => static::map($row), $stmt->fetchAll());
        }

        public static function findOne(array $conditions): ?static{
            $results = static::findBy($conditions, null, 1);
            return $results[0] ?? null;
        }

        // ── Instance methods ────────────────────────────────────────────────────

        public function save(): void{
            $pdo     = static::connect();
            $table   = static::getTableName();
            $pk      = static::getPrimaryKey();
            $pkCol   = static::getColumns()[$pk];
            $columns = static::getColumns();
            $ref     = new ReflectionClass(static::class);

            $prop = $ref->getProperty($pk);
            $prop->setAccessible(true);
            $pkValue = $prop->getValue($this);

            if($pkValue === null){
                // INSERT — exclude PK column
                $cols   = [];
                $params = [];
                foreach($columns as $propName => $colName){
                    if($propName === $pk) continue;
                    $p = $ref->getProperty($propName);
                    $p->setAccessible(true);
                    if($p->isInitialized($this)){
                        $cols[]   = "`$colName`";
                        $params[] = $p->getValue($this);
                    }
                }
                $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                $sql = "INSERT INTO `$table` (" . implode(', ', $cols) . ") VALUES ($placeholders)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $prop->setValue($this, $pdo->lastInsertId());
            } else {
                // UPDATE
                $sets   = [];
                $params = [];
                foreach($columns as $propName => $colName){
                    if($propName === $pk) continue;
                    $p = $ref->getProperty($propName);
                    $p->setAccessible(true);
                    if($p->isInitialized($this)){
                        $sets[]   = "`$colName` = ?";
                        $params[] = $p->getValue($this);
                    }
                }
                $params[] = $pkValue;
                $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE `$pkCol` = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }
        }

        public function delete(): void{
            $pdo   = static::connect();
            $table = static::getTableName();
            $pk    = static::getPrimaryKey();
            $pkCol = static::getColumns()[$pk];
            $ref   = new ReflectionClass(static::class);
            $prop  = $ref->getProperty($pk);
            $prop->setAccessible(true);
            $pkValue = $prop->getValue($this);
            $stmt = $pdo->prepare("DELETE FROM `$table` WHERE `$pkCol` = ?");
            $stmt->execute([$pkValue]);
        }
    }
}
?>
