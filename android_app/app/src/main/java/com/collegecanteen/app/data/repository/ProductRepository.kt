package com.collegecanteen.app.data.repository

import com.collegecanteen.app.data.model.Product
import com.collegecanteen.app.data.remote.RetrofitClient

class ProductRepository {
    private val api = RetrofitClient.apiService

    suspend fun getProducts(search: String? = null): Result<List<Product>> {
        return try {
            val response = api.getProducts(search)
            if (response.isSuccessful) {
                val body = response.body()
                if (body != null && body.success && body.data != null) {
                    Result.success(body.data.products)
                } else {
                    Result.failure(Exception(body?.message ?: "Failed to fetch products"))
                }
            } else {
                Result.failure(Exception("Error: ${response.code()} ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
