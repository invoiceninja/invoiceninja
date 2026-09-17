<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('user_id');
            $table->string('entity_type', 40);
            $table->string('name', 191);
            $table->string('color', 20)->nullable();
            $table->boolean('is_deleted')->default(0);
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);

            $table->index(['company_id', 'entity_type']);
            $table->unique(['company_id', 'entity_type', 'name']);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tag_id');
            $table->unsignedInteger('taggable_id');
            $table->string('taggable_type', 191);

            $table->unique(['tag_id', 'taggable_id', 'taggable_type'], 'taggables_unique');
            $table->index(['taggable_id', 'taggable_type'], 'taggables_morph_index');

            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
    }
};
