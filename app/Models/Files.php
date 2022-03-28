<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Files extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'files';

    const STAT_FILE_TYPE = 'stat';
    const ERROR_FILE_TYPE = 'error';

    const REPORTS_PATH = '/files/reports/';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function massUpdate()
    {
        return $this->belongsTo('App\Models\MassUpdate', 'update_id');
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function shop()
    {
        return $this->belongsTo('\App\Models\User', 'shop_id');
    }



}
