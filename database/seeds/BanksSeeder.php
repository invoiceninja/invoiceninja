<?php

use App\Models\Bank;

class BanksSeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();

        $this->createBanks();
    }

    // Source: http://www.ofxhome.com/
    private function createBanks()
    {
        $banks = [
            [
                'remote_id' => 425,
                'name' => 'American Express Card',
                'config' => json_encode([
                    'fid' => 3101,
                    'org' => 'AMEX',
                    'url' => 'https://online.americanexpress.com/myca/ofxdl/desktop/desktopDownload.do?request_type=nl_ofxdownload',
                ])
            ],
            [
                'remote_id' => 497,
                'name' => 'AIM Investments',
                'config' => json_encode([
                    'fid' => '',
                    'org' => '',
                    'url' => 'https://ofx3.financialtrans.com/tf/OFXServer?tx=OFXController&amp;cz=702110804131918&amp;cl=3000812',
                ])
            ],
        ];

        foreach ($banks as $bank) {
            if (!DB::table('banks')->where('remote_id', '=', $bank['remote_id'])->get()) {
                Bank::create($bank);
            }
        }
    }
}
