<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id(); // pdc_id
            $table->string('name'); // pdc_name
            $table->text('description')->nullable(); // pdc_description
            $table->integer('price'); // pdc_price
            $table->integer('stock'); // pdc_stock

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable(); // pdc_created_by
            $table->unsignedBigInteger('updated_by')->nullable(); // pdc_updated_by
            $table->unsignedBigInteger('deleted_by')->nullable(); // pdc_deleted_by

            $table->timestamps(); // pdc_created_at, pdc_updated_at
            $table->softDeletes(); // pdc_deleted_at
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
}
