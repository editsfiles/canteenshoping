package com.collegecanteen.app.data.model

import com.google.gson.annotations.SerializedName

data class OrderRequest(
    @SerializedName("user_id")
    val userId: Int,
    @SerializedName("token")
    val token: String,
    @SerializedName("items")
    val items: List<OrderItemRequest>
)

data class OrderItemRequest(
    @SerializedName("product_id")
    val productId: Int,
    @SerializedName("quantity")
    val quantity: Int
)
