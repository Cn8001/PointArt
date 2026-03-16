<?php
use PointStart\Attributes\Router;
use PointStart\Attributes\Route;
use PointStart\Attributes\HttpMethod;
use PointStart\Attributes\RequestParam;
use PointStart\Core\Renderer;
use PointStart\Attributes\Wired;

#[Router(name: 'product', path: '/product')]
class ProductController {

    #[Wired]
    private ProductRepository $productRepository;

    // GET /product/list
    #[Route('/list', HttpMethod::GET)]
    public function index(): string {
        $products = $this->productRepository->findAllActive();
        return Renderer::render('product.list', ['products' => $products]);
    }

    // GET /product/show/{id}
    #[Route('/show/{id}', HttpMethod::GET)]
    public function show(int $id): string {
        $product = Product::find($id);
        if ($product === null) {
            return Renderer::render('product.notfound');
        }
        return Renderer::render('product.show', ['product' => $product]);
    }

    // GET /product/search?name=foo
    #[Route('/search', HttpMethod::GET)]
    public function search(string $name): string {
        $products = $this->productRepository->findByName($name);
        return Renderer::render('product.list', ['products' => $products]);
    }

    // GET /product/affordable?maxPrice=50
    #[Route('/affordable', HttpMethod::GET)]
    public function affordable(float $maxPrice = 50): string {
        $products = $this->productRepository->findAffordable($maxPrice);
        return Renderer::render('product.list', ['products' => $products]);
    }

    // GET /product/low-stock?threshold=5
    #[Route('/low-stock', HttpMethod::GET)]
    public function lowStock(int $threshold = 5): string {
        $products = $this->productRepository->findByStockLessThan($threshold);
        return Renderer::render('product.list', ['products' => $products]);
    }

    // POST /product/create
    #[Route('/create', HttpMethod::POST)]
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
    #[Route('/update/{id}', HttpMethod::POST)]
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
    #[Route('/delete/{id}', HttpMethod::POST)]
    public function delete(int $id): string {
        $this->productRepository->deleteById($id);
        $products = $this->productRepository->findAllActive();
        return Renderer::render('product.list', ['products' => $products]);
    }
}
?>
