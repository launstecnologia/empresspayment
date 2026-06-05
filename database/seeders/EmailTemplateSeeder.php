<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Support\EmailTemplateCatalogo;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (EmailTemplateCatalogo::padroes() as $template) {
            EmailTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                $template
            );
        }
    }
}
