<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collections extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'collections';



    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function shop()
    {
        return $this->belongsTo('\App\Models\User', 'shop_id');
    }
}
