package com.collegecanteen.app.ui.orders

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.collegecanteen.app.data.model.Order
import com.collegecanteen.app.data.repository.OrderListRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

class OrdersViewModel : ViewModel() {
    private val repository = OrderListRepository()

    private val _uiState = MutableStateFlow<OrdersUiState>(OrdersUiState.Loading)
    val uiState: StateFlow<OrdersUiState> = _uiState

    fun loadOrders(userId: Int, token: String) {
        viewModelScope.launch {
            _uiState.value = OrdersUiState.Loading
            val result = repository.getOrders(userId, token)
            result.onSuccess { orders ->
                _uiState.value = OrdersUiState.Success(orders)
            }.onFailure { exception ->
                _uiState.value = OrdersUiState.Error(exception.message ?: "Failed to load orders")
            }
        }
    }
}

sealed class OrdersUiState {
    object Loading : OrdersUiState()
    data class Success(val orders: List<Order>) : OrdersUiState()
    data class Error(val message: String) : OrdersUiState()
}
