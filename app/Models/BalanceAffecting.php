<?php namespace App\Models;

interface BalanceAffecting
{
    public function getAdjustment();
}