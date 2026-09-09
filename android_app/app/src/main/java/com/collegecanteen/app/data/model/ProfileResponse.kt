package com.collegecanteen.app.data.model

import com.google.gson.annotations.SerializedName

data class ProfileResponse(
    val success: Boolean,
    val message: String,
    val data: ProfileData?
)

data class ProfileData(
    val id: Int,
    val name: String,
    val email: String,
    val phone: String
)
