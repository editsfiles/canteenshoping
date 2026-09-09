package com.collegecanteen.app.ui.orders

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.collegecanteen.app.data.model.OrderDetailsData
import com.collegecanteen.app.data.remote.RetrofitClient
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed class OrderDetailsUiState {
    object Loading : OrderDetailsUiState()
    data class Success(val orderDetails: OrderDetailsData) : OrderDetailsUiState()
    data class Error(val message: String) : OrderDetailsUiState()
}

class OrderDetailsViewModel : ViewModel() {
    private val _uiState = MutableStateFlow<OrderDetailsUiState>(OrderDetailsUiState.Loading)
    val uiState: StateFlow<OrderDetailsUiState> = _uiState.asStateFlow()

    fun loadOrderDetails(userId: Int, token: String, orderId: Int) {
        viewModelScope.launch {
            _uiState.value = OrderDetailsUiState.Loading
            try {
                val response = RetrofitClient.apiService.getOrderDetails(userId, token, orderId)
                if (response.isSuccessful && response.body()?.success == true) {
                    val data = response.body()?.data
                    if (data != null) {
                        _uiState.value = OrderDetailsUiState.Success(data)
                    } else {
                        _uiState.value = OrderDetailsUiState.Error("Order details data missing")
                    }
                } else {
                    _uiState.value = OrderDetailsUiState.Error(response.body()?.message ?: "Failed to load order details")
                }
            } catch (e: Exception) {
                _uiState.value = OrderDetailsUiState.Error(e.message ?: "Network error")
            }
        }
    }
}
