<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Separate "we wrote a reply" from "the reply is public".
 *
 * ReputationService::respond() sets responded = true and stores the text, and
 * the audit records content.review.responded. Nothing goes anywhere: the
 * ReviewProvider contract has only fetch(), so there is no way to post a reply
 * to Google, Clutch or anywhere else, on any driver.
 *
 * A user answering a one-star review therefore sees it marked as responded and
 * reasonably concludes the public has seen their answer. They have not. That is
 * a statement about the outside world the product cannot support.
 *
 * response_published_at records when a reply actually reached the platform. It
 * is null for every existing row and stays null until a driver can publish one,
 * which makes the limitation explicit in the data rather than implicit in the
 * absence of an interface method.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->timestamp('response_published_at')->nullable()->after('response');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('response_published_at');
        });
    }
};
