package com.collegecanteen.app.data.model

import com.google.gson.annotations.SerializedName

data class Product(
    @SerializedName("id")
    val id: Int,
    @SerializedName("name")
    val name: String,
    @SerializedName("description")
    val description: String?,
    @SerializedName("price")
    val price: Double,
    @SerializedName("image_url")
    val imageUrl: String?,
    @SerializedName("status")
    val status: String
)
