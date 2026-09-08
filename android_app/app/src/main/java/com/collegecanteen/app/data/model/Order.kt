package com.collegecanteen.app.data.model

import com.google.gson.annotations.SerializedName

data class OrdersListResponse(
    @SerializedName("success")
    val success: Boolean,
    @SerializedName("message")
    val message: String,
    @SerializedName("data")
    val data: OrdersListData?
)

data class OrdersListData(
    @SerializedName("orders")
    val orders: List<Order>
)

data class Order(
    @SerializedName("id")
    val id: Int,
    @SerializedName("total_amount")
    val totalAmount: Double,
    @SerializedName("payment_method")
    val paymentMethod: String,
    @SerializedName("status")
    val status: String,
    @SerializedName("food_status")
    val foodStatus: String,
    @SerializedName("order_date")
    val orderDate: String
)
