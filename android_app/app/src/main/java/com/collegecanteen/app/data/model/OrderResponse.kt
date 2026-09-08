package com.collegecanteen.app.data.model

import com.google.gson.annotations.SerializedName

data class OrderResponse(
    @SerializedName("success")
    val success: Boolean,
    @SerializedName("message")
    val message: String,
    @SerializedName("data")
    val data: OrderData?
)

data class OrderData(
    @SerializedName("order_id")
    val orderId: Int,
    @SerializedName("total_amount")
    val totalAmount: Double,
    @SerializedName("status")
    val status: String
)
