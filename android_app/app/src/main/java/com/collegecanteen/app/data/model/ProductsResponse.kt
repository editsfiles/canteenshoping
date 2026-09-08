package com.collegecanteen.app.data.model

import com.google.gson.annotations.SerializedName

data class ProductsResponse(
    @SerializedName("success")
    val success: Boolean,
    @SerializedName("message")
    val message: String,
    @SerializedName("data")
    val data: ProductsData?
)

data class ProductsData(
    @SerializedName("products")
    val products: List<Product>
)
