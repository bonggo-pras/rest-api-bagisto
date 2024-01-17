<?php

namespace Webkul\RestApi\Http\Resources\V1\Shop\Catalog;

use Illuminate\Http\Resources\Json\JsonResource;
use Webkul\Product\Facades\ProductImage;

class ProductVariantResource extends JsonResource
{
    public function toArray($request)
    {
        $productToArray = $this->resource->toArray();

        $product = $this->product ? $this->product : $this;
        
        /* get type instance */
        $productTypeInstance = $product->getTypeInstance();
        $inventories = $product->inventories()->get();
       
        return array_merge($productToArray, [
            'formatted_price'    => core()->currency($productTypeInstance->getMinimalPrice()),
            'inventories' => $inventories->toArray()
        ]);
    }
}
