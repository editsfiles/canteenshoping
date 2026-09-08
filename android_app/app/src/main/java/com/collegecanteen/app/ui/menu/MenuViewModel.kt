package com.collegecanteen.app.ui.menu

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.collegecanteen.app.data.model.Product
import com.collegecanteen.app.data.repository.ProductRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

class MenuViewModel : ViewModel() {

    private val repository = ProductRepository()

    private val _uiState = MutableStateFlow<MenuUiState>(MenuUiState.Loading)
    val uiState: StateFlow<MenuUiState> = _uiState

    init {
        loadProducts()
    }

    fun loadProducts(searchQuery: String? = null) {
        viewModelScope.launch {
            _uiState.value = MenuUiState.Loading
            val result = repository.getProducts(searchQuery)
            result.onSuccess { products ->
                _uiState.value = MenuUiState.Success(products)
            }.onFailure { exception ->
                _uiState.value = MenuUiState.Error(exception.message ?: "An unexpected error occurred")
            }
        }
    }
}

sealed class MenuUiState {
    object Loading : MenuUiState()
    data class Success(val products: List<Product>) : MenuUiState()
    data class Error(val message: String) : MenuUiState()
}
