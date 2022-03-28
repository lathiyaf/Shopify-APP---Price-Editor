<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MassUpdate extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mass_update';

    const UPDATE_STATUS_OLD = 'old';
    const UPDATE_STATUS_RUNNING = 'running';
    const UPDATE_STATUS_PAUSED = 'paused';
    const UPDATE_STATUS_FINISHED = 'finished';
    const UPDATE_STATUS_FAILED = 'failed';
    const UPDATE_STATUS_REVERTING = 'reverting';
    const UPDATE_STATUS_REVERTING_PAUSED = 'reverting_paused';
    const UPDATE_STATUS_REVERTING_FINISHED = 'reverting_finished';
    const UPDATE_STATUS_REVERTING_FAILED = 'reverting_failed';


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
        return $this->hasMany('App\Models\UpdatedProducts', 'update_id', 'id');
    }

    /**
     * @return  \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function reverted_updates()
    {
        return $this->hasMany('App\Models\UpdatedProducts', 'update_id', 'id')
            ->where('reverted', 1);
    }



    /**
     * @return HasOne
     */
    public function file()
    {
        return $this
            ->hasOne(Files::class, 'update_id')->where('type', Files::STAT_FILE_TYPE);
    }

    /**
     * @return  \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function error_files()
    {
        return $this->hasMany('App\Models\Files', 'update_id', 'id')
            ->where('type', Files::ERROR_FILE_TYPE);
    }




    public static function isMassUpdateRunning($id)
    {
        $massUpdate = self::select('status')->find($id);

        return $massUpdate->status == self::UPDATE_STATUS_RUNNING;
    }


    public static function isMassUpdateReverting($id)
    {
        $massUpdate = self::select('status')->find($id);

        return $massUpdate->status == self::UPDATE_STATUS_REVERTING;
    }


    public function getStatusTextAttribute(): ?string
    {
        switch ($this->status) {
            case self::UPDATE_STATUS_RUNNING: {
                return 'Running';
                break;
            }
            case self::UPDATE_STATUS_PAUSED: {
                return 'Paused';
                break;
            }
            case self::UPDATE_STATUS_FINISHED: {
                return 'Finished';
                break;
            }
            case self::UPDATE_STATUS_FAILED: {
                return 'Failed';
                break;
            }
            case self::UPDATE_STATUS_REVERTING: {
                return 'Rollback in progress';
                break;
            }
            case self::UPDATE_STATUS_REVERTING_PAUSED: {
                return 'Rollback paused';
                break;
            }
            case self::UPDATE_STATUS_REVERTING_FINISHED: {
                return 'Rollback finished';
                break;
            }
            case self::UPDATE_STATUS_REVERTING_FAILED: {
                return 'Rollback failed';
                break;
            }
            default: {
                return 'Unknown';
                break;
            }
        }
    }





}
