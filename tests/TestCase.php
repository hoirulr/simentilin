<?php

namespace Tests;

use App\Models\Desa;
use App\Models\Kecamatan;
use App\Models\PusProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * @property User $user
 * @property User $admin
 * @property User $petugas
 * @property Kecamatan $kecamatan
 * @property Desa $desa
 * @property PusProfile $pus
 */
abstract class TestCase extends BaseTestCase
{
    //
}
