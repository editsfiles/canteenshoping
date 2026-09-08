package com.collegecanteen.app.data.model

data class ApiResponse<T>(
    val success: Boolean,
    val message: String,
    val data: T?
)

data class AuthData(
    val user: User
)
