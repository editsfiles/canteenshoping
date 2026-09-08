package com.collegecanteen.app.data.repository

import com.collegecanteen.app.data.model.Order
import com.collegecanteen.app.data.remote.RetrofitClient

class OrderListRepository {
    private val api = RetrofitClient.apiService

    suspend fun getOrders(userId: Int, token: String): Result<List<Order>> {
        return try {
            val response = api.getOrders(userId, token)
            if (response.isSuccessful) {
                val body = response.body()
                if (body != null && body.success && body.data != null) {
                    Result.success(body.data.orders)
                } else {
                    Result.failure(Exception(body?.message ?: "Failed to fetch orders"))
                }
            } else {
                Result.failure(Exception("Error: ${response.code()} ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
