<?php

namespace Webkul\RestApi\Http\Resources\V1\Shop\Catalog;

use Illuminate\Http\Resources\Json\JsonResource;

class InventoryProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'qty' => $this->qty,
            'product_id' => $this->product_id,
            'inventory_source_id' => $this->inventory_source_id,
            'vendor_id' => $this->vendor_id,
        ];
    }
}
