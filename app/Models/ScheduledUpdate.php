<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledUpdate extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'scheduled_update';

    const UPDATE_STATUS_ACTIVE = 'active';
    const UPDATE_STATUS_RUNNING = 'running';
    const UPDATE_STATUS_CANCELED = 'canceled';
    const UPDATE_STATUS_FINISHED = 'finished';

    const UPDATE_TYPE_PERIOD = 'period';
    const UPDATE_TYPE_DAILY = 'daily';
    const UPDATE_TYPE_WEEKLY = 'weekly';
    const UPDATE_TYPE_MONTHLY = 'monthly';

    protected $appends = ['status_text'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function shop()
    {
        return $this->belongsTo('\App\Models\User', 'shop_id');
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function updates()
    {
        return $this->hasMany('App\Models\MassUpdate', 'scheduled_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function lastUpdate()
    {
        return $this->hasOne('App\Models\MassUpdate', 'scheduled_id', 'id')->latest();
    }


    public function getStatusTextAttribute(): ?string
    {
        switch ($this->status) {
            case self::UPDATE_STATUS_RUNNING: {
                return 'Active';
                break;
            }
            case self::UPDATE_STATUS_ACTIVE: {
                return 'Active';
                break;
            }
            case self::UPDATE_STATUS_CANCELED: {
                return 'Canceled';
                break;
            }
            case self::UPDATE_STATUS_FINISHED: {
                return 'Canceled';
                break;
            }
            default: {
                return 'Unknown';
                break;
            }
        }
    }





}
