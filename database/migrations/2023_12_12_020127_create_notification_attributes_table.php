<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\SerializableClosure\UnsignedSerializableClosure;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('notification_id')->unique();
            $table->unsignedBigInteger('group_id')->nullable();

            $table->foreign('notification_id')->references('id')->on('notifications');
            $table->foreign('group_id')->references('id')->on('groups');

            $table->index('notification_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_attributes');
    }
};
