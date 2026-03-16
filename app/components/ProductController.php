<?php
use PointStart\Attributes\Router;
use PointStart\Attributes\Route;
use PointStart\Attributes\RequestParam;
use PointStart\Core\Renderer;
use PointStart\Attributes\Wired;

#[Router(name: 'product', path: '/product')]
class ProductController {

    #[Wired]
    private ProductRepository $productRepository;

    public function __construct() {
        $this->productRepository = ProductRepository::make();
    }

    // GET /product/list
    #[Route('/list', 'GET')]
    public function index(): string {
        $products = $this->productRepository->findAllActive();
        return Renderer::render('product.list', ['products' => $products]);
    }

    // GET /product/show/{id}
    #[Route('/show/{id}', 'GET')]
    public function show(int $id): string {
        $product = Product::find($id);
        if ($product === null) {
            return Renderer::render('product.notfound');
        }
        return Renderer::render('product.show', ['product' => $product]);
    }

    // GET /product/search?name=foo
    #[Route('/search', 'GET')]
    public function search(string $name): string {
        $products = $this->productRepository->findByName($name);
        return Renderer::render('product.list', ['products' => $products]);
    }

    // GET /product/affordable?maxPrice=50
    #[Route('/affordable', 'GET')]
    public function affordable(float $maxPrice = 50): string {
        $products = $this->productRepository->findAffordable($maxPrice);
        return Renderer::render('product.list', ['products' => $products]);
    }

    // GET /product/low-stock?threshold=5
    #[Route('/low-stock', 'GET')]
    public function lowStock(int $threshold = 5): string {
        $products = $this->productRepository->findByStockLessThan($threshold);
        return Renderer::render('product.list', ['products' => $products]);
    }

    // POST /product/create
    #[Route('/create', 'POST')]
    public function create(
        #[RequestParam] string $name,
        #[RequestParam] float  $price,
        #[RequestParam] int    $stock
    ): string {
        $product        = new Product();
        $product->name  = $name;
        $product->price = $price;
        $product->stock = $stock;
        $product->save();
        return Renderer::render('product.show', ['product' => $product]);
    }

    // POST /product/update/{id}
    #[Route('/update/{id}', 'POST')]
    public function update(
        int $id,
        #[RequestParam] float $price,
        #[RequestParam] int   $stock
    ): string {
        $product = Product::find($id);
        if ($product === null) {
            return Renderer::render('product.notfound');
        }
        $product->price = $price;
        $product->stock = $stock;
        $product->save();
        return Renderer::render('product.show', ['product' => $product]);
    }

    // POST /product/delete/{id}
    #[Route('/delete/{id}', 'POST')]
    public function delete(int $id): string {
        $this->productRepository->deleteById($id);
        $products = $this->productRepository->findAllActive();
        return Renderer::render('product.list', ['products' => $products]);
    }
}
?>
