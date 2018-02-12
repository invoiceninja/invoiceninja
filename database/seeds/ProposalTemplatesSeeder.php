<?php

use App\Models\ProposalTemplate;

class ProposalTemplatesSeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();

        $designs = [
            'Clean',
            'Bold',
            'Modern',
            'Plain',
            'Business',
            'Creative',
            'Elegant',
            'Hipster',
            'Playful',
            'Photo',
        ];

        for ($i = 0; $i < count($designs); $i++) {
            $design = $designs[$i];
            $baseFileName = storage_path() . '/templates/' . strtolower($design);
            $htmlFileName = $baseFileName . '.html';
            $cssFileName = $baseFileName . '.css';
            if (file_exists($htmlFileName) && file_exists($cssFileName)) {
                $template = ProposalTemplate::whereName($design)->whereNull('account_id')->first();

                if (! $template) {
                    $template = new ProposalTemplate();
                    $template->public_id = $i + 1;
                    $template->name = $design;
                }

                $template->html = file_get_contents($htmlFileName);
                $template->css = file_get_contents($cssFileName);
                $template->save();
            }
        }
    }
}
