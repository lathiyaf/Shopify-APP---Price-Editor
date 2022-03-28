<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UpdatedProducts extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'updated_products';



    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function massUpdate()
    {
        return $this->belongsTo('App\Models\MassUpdate', 'update_id');
    }


    public static function updateExists($massUpdateId, $productId, $variantId)
    {
        $update = self::select('id')
            ->where('update_id', $massUpdateId)
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->first();

        return !empty($update) ? 1 : 0;
    }

    public static function countReverted($massUpdateId)
    {
        return self::where('update_id', $massUpdateId)
            ->where('reverted', 1)->count();
    }

    public static function countVariants($massUpdateId)
    {
        return self::where('update_id', $massUpdateId)
                ->count();
    }

    public static function countProducts($massUpdateId)
    {
        return self::where('update_id', $massUpdateId)
            ->distinct('product_id')
            ->count('product_id');
    }


    public static function getLastUpdatedProduct($massUpdateId)
    {
        $update = self::select('product_id')
            ->where('update_id', $massUpdateId)
            ->orderBy('product_id', 'DESC')
            ->limit(1)
            ->first();

        return !empty($update) ? $update->product_id : 0;
    }
}
