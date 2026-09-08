package com.collegecanteen.app.data.remote

import com.collegecanteen.app.data.model.ApiResponse
import com.collegecanteen.app.data.model.AuthData
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.POST

interface ApiService {
    
    @POST("auth/login.php")
    suspend fun login(@Body body: Map<String, String>): Response<ApiResponse<AuthData>>
    
    @POST("auth/register.php")
    suspend fun register(@Body body: Map<String, String>): Response<ApiResponse<AuthData>>

    @retrofit2.http.GET("products/list.php")
    suspend fun getProducts(@retrofit2.http.Query("search") search: String? = null): Response<com.collegecanteen.app.data.model.ProductsResponse>

    @POST("orders/create.php")
    suspend fun createOrder(@Body request: com.collegecanteen.app.data.model.OrderRequest): Response<com.collegecanteen.app.data.model.OrderResponse>

    @retrofit2.http.GET("orders/list.php")
    suspend fun getOrders(
        @retrofit2.http.Query("user_id") userId: Int,
        @retrofit2.http.Query("token") token: String
    ): Response<com.collegecanteen.app.data.model.OrdersListResponse>
}
