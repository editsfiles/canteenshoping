package com.collegecanteen.app.data.repository

import com.collegecanteen.app.data.model.OrderRequest
import com.collegecanteen.app.data.model.OrderResponse
import com.collegecanteen.app.data.remote.RetrofitClient

class OrderRepository {
    private val api = RetrofitClient.apiService

    suspend fun createOrder(request: OrderRequest): Result<OrderResponse> {
        return try {
            val response = api.createOrder(request)
            if (response.isSuccessful) {
                val body = response.body()
                if (body != null && body.success) {
                    Result.success(body)
                } else {
                    Result.failure(Exception(body?.message ?: "Failed to create order"))
                }
            } else {
                Result.failure(Exception("Error: ${response.code()} ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
