<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            // Onboarding status
            $table->string('onboarding_status', 30)->default('step1')->after('notes');
            // step1 | pending_approval | step2 | completed

            // Personal (step 1)
            $table->unsignedTinyInteger('age')->nullable()->after('display_name');
            $table->text('description')->nullable()->after('age');
            $table->string('audio_path')->nullable()->after('description');
            $table->string('photo_main')->nullable()->after('audio_path');
            $table->json('photos_gallery')->nullable()->after('photo_main');
            $table->json('favorite_games')->nullable()->after('photos_gallery');
            $table->json('favorite_anime')->nullable()->after('favorite_games');
            $table->string('experience', 100)->nullable()->after('favorite_anime');
            $table->string('preferred_format', 100)->nullable()->after('experience');

            // Services (step 2)
            $table->json('services')->nullable()->after('preferred_format');
            $table->json('schedule_preferences')->nullable()->after('services');

            // Admin notes on approval
            $table->text('onboarding_notes')->nullable()->after('onboarding_status');
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn([
                'onboarding_status',
                'onboarding_notes',
                'age',
                'description',
                'audio_path',
                'photo_main',
                'photos_gallery',
                'favorite_games',
                'favorite_anime',
                'experience',
                'preferred_format',
                'services',
                'schedule_preferences',
            ]);
        });
    }
};
