<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;

class AccountsTableSeeder extends Seeder
{
    public function run()
    {
        $data = [
            ['code'=>'1000','name'=>'Kas','type'=>Account::TYPE_ASSET,'is_cash'=>true],
            ['code'=>'1100','name'=>'Bank','type'=>Account::TYPE_ASSET,'is_cash'=>true],
            ['code'=>'1200','name'=>'Piutang Usaha','type'=>Account::TYPE_ASSET],
            ['code'=>'2000','name'=>'Hutang Usaha','type'=>Account::TYPE_LIAB],
            ['code'=>'3000','name'=>'Modal','type'=>Account::TYPE_EQUITY],
            ['code'=>'4000','name'=>'Pendapatan','type'=>Account::TYPE_REVENUE],
            ['code'=>'5000','name'=>'Beban Operasional','type'=>Account::TYPE_EXPENSE],
        ];
        foreach ($data as $d) {
            Account::firstOrCreate(['code'=>$d['code']], $d + ['is_active'=>true]);
        }
    }
}
