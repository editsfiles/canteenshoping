package com.collegecanteen.app.data.repository

import com.collegecanteen.app.data.local.SecureSessionManager
import com.collegecanteen.app.data.remote.ApiService
import com.collegecanteen.app.data.model.ApiResponse
import com.collegecanteen.app.data.model.AuthData
import retrofit2.Response

class AuthRepository(
    private val apiService: ApiService,
    private val sessionManager: SecureSessionManager
) {
    suspend fun login(email: String, password: String): Result<AuthData> {
        return try {
            val response = apiService.login(mapOf("email" to email, "password" to password))
            handleAuthResponse(response)
        } catch (e: Exception) {
            Result.failure(Exception("Network error: ${e.message}"))
        }
    }
    
    suspend fun register(name: String, email: String, phone: String, password: String): Result<AuthData> {
        return try {
            val response = apiService.register(
                mapOf("name" to name, "email" to email, "phone" to phone, "password" to password)
            )
            handleAuthResponse(response)
        } catch (e: Exception) {
            Result.failure(Exception("Network error: ${e.message}"))
        }
    }
    
    private fun handleAuthResponse(response: Response<ApiResponse<AuthData>>): Result<AuthData> {
        val body = response.body()
        return if (response.isSuccessful && body != null) {
            if (body.success && body.data != null) {
                // Save session securely
                sessionManager.saveSession(body.data.user)
                Result.success(body.data)
            } else {
                Result.failure(Exception(body.message))
            }
        } else {
            Result.failure(Exception("Server error"))
        }
    }
    
    fun logout() {
        sessionManager.clearSession()
    }
}
