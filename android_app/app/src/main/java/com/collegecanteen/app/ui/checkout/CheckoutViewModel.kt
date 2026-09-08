package com.collegecanteen.app.ui.checkout

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.collegecanteen.app.data.model.OrderItemRequest
import com.collegecanteen.app.data.model.OrderRequest
import com.collegecanteen.app.data.model.OrderResponse
import com.collegecanteen.app.data.repository.CartRepository
import com.collegecanteen.app.data.repository.OrderRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

class CheckoutViewModel : ViewModel() {
    private val repository = OrderRepository()

    private val _uiState = MutableStateFlow<CheckoutUiState>(CheckoutUiState.Idle)
    val uiState: StateFlow<CheckoutUiState> = _uiState

    fun placeOrder(userId: Int, token: String) {
        val cartItems = CartRepository.cartItems.value
        if (cartItems.isEmpty()) {
            _uiState.value = CheckoutUiState.Error("Cart is empty")
            return
        }

        val orderItems = cartItems.map { OrderItemRequest(it.product.id, it.quantity) }
        val request = OrderRequest(userId, token, orderItems)

        viewModelScope.launch {
            _uiState.value = CheckoutUiState.Loading
            val result = repository.createOrder(request)
            result.onSuccess { response ->
                CartRepository.clearCart() // Clear cart on success
                _uiState.value = CheckoutUiState.Success(response)
            }.onFailure { exception ->
                _uiState.value = CheckoutUiState.Error(exception.message ?: "Failed to place order")
            }
        }
    }
}

sealed class CheckoutUiState {
    object Idle : CheckoutUiState()
    object Loading : CheckoutUiState()
    data class Success(val response: OrderResponse) : CheckoutUiState()
    data class Error(val message: String) : CheckoutUiState()
}
