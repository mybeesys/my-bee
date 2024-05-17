<?php

namespace App\Traits;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;

trait Workfloable
{
    use Queueable, Dispatchable;
    public function sendSms()
    {

    }

    public function email()
    {

    }

}
