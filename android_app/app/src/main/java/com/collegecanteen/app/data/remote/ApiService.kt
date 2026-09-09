package com.collegecanteen.app.data.remote

import com.collegecanteen.app.data.model.ApiResponse
import com.collegecanteen.app.data.model.AuthData
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.POST
import retrofit2.http.GET
import retrofit2.http.FormUrlEncoded
import retrofit2.http.Field
import retrofit2.http.Query

interface ApiService {
    
    @POST("auth/login.php")
    suspend fun login(@Body body: Map<String, String>): Response<ApiResponse<AuthData>>
    
    @POST("auth/register.php")
    suspend fun register(@Body body: Map<String, String>): Response<ApiResponse<AuthData>>

    @FormUrlEncoded
    @POST("auth/send_otp.php")
    suspend fun sendOtp(@Field("email") email: String): ApiResponse<Any>

    @FormUrlEncoded
    @POST("auth/verify_otp.php")
    suspend fun verifyOtp(@Field("email") email: String, @Field("otp") otp: String): ApiResponse<Any>

    @FormUrlEncoded
    @POST("auth/reset_password.php")
    suspend fun resetPassword(
        @Field("email") email: String,
        @Field("otp") otp: String,
        @Field("password") password: String
    ): ApiResponse<Any>

    @GET("products/list.php")
    suspend fun getProducts(@Query("search") search: String? = null): Response<com.collegecanteen.app.data.model.ProductsResponse>

    @POST("orders/create.php")
    suspend fun createOrder(@Body request: com.collegecanteen.app.data.model.OrderRequest): Response<com.collegecanteen.app.data.model.OrderResponse>

    @GET("orders/list.php")
    suspend fun getOrders(
        @Query("user_id") userId: Int,
        @Query("token") token: String
    ): Response<com.collegecanteen.app.data.model.OrdersListResponse>

    @GET("orders/details.php")
    suspend fun getOrderDetails(
        @Query("user_id") userId: Int,
        @Query("token") token: String,
        @Query("order_id") orderId: Int
    ): Response<com.collegecanteen.app.data.model.OrderDetailsResponse>

    @GET("auth/get_profile.php")
    suspend fun getProfile(
        @Query("user_id") userId: Int,
        @Query("token") token: String
    ): Response<com.collegecanteen.app.data.model.ProfileResponse>

    @FormUrlEncoded
    @POST("auth/update_profile.php")
    suspend fun updateProfile(
        @Field("user_id") userId: Int,
        @Field("token") token: String,
        @Field("name") name: String,
        @Field("phone") phone: String
    ): ApiResponse<Any>
}
