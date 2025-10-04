<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionItemsTable extends Migration
{
    public function up()
    {
       Schema::create('transaction_items', function (Blueprint $table) {
  $table->id();
$table->foreignId('transaction_id')->constrained()->onDelete('cascade');
$table->foreignId('product_id')->constrained()->onDelete('cascade');
$table->integer('quantity');
$table->integer('price');
$table->integer('subtotal');
$table->timestamps();
// Audit fields
$table->unsignedBigInteger('created_by')->nullable();
$table->unsignedBigInteger('updated_by')->nullable();
$table->unsignedBigInteger('deleted_by')->nullable();
$table->softDeletes();
});

    }

    public function down()
    {
        Schema::dropIfExists('transaction_items');
    }
}
