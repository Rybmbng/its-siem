<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('agents', function (Blueprint $table) {
        $table->id();
        $table->string('name');         // Nama server (prod-web-01)
        $table->string('api_key')->unique(); // Key unik untuk Agent ini
        $table->string('ip_address')->nullable();
        $table->string('status')->default('active');
        $table->timestamp('last_seen_at')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
