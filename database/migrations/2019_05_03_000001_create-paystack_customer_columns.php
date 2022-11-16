<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::table('paystack_customers', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('billable_id');
      $table->string('billable_type');
      $table->string('paystack_email_token')->nullable();
      $table->string('paystack_id')->nullable()->index();
      $table->string('paystack_code')->nullable();
      $table->string('paystack_authorization')->nullable();
      $table->string('card_brand')->nullable();
      $table->string('card_last_four', 4)->nullable();
      $table->timestamp('trial_ends_at')->nullable();

      $table->index(['billable_id', 'billable_type']);
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::dropIfExists('paystack_customers');
  }
};
