package com.collegecanteen.app.data.model

import com.google.gson.annotations.SerializedName

data class OrderDetailsResponse(
    val success: Boolean,
    val message: String,
    val data: OrderDetailsData?
)

data class OrderDetailsData(
    @SerializedName("order_id") val orderId: Int,
    val items: List<OrderItem>
)

data class OrderItem(
    val id: Int,
    @SerializedName("product_id") val productId: Int,
    @SerializedName("product_name") val productName: String,
    val quantity: Int,
    val price: Double,
    @SerializedName("image_url") val imageUrl: String
)
