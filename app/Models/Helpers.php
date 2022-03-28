<?php

namespace App\Models;

class Helpers
{

    public static function convertDateToUtc($datetime, $timezone)
    {
        if(empty($timezone) || empty($datetime)){
            return $datetime;
        }
        $given = new \DateTime($datetime, new \DateTimeZone($timezone));
        $given->setTimezone(new \DateTimeZone("GMT"));
        $output = $given->format("Y-m-d H:i:s");
        return $output;
    }

    public static function convertUtcToDate($datetime, $timezone)
    {
        if(empty($timezone) || empty($datetime)){
            return $datetime;
        }
        $given = new \DateTime($datetime);
        $given->setTimezone(new \DateTimeZone($timezone));
        $output = $given->format("Y-m-d H:i:s");
        return $output;
    }


}
