package com.collegecanteen.app.ui.cart

import androidx.lifecycle.ViewModel
import com.collegecanteen.app.data.model.Product
import com.collegecanteen.app.data.repository.CartRepository

class CartViewModel : ViewModel() {
    val cartItems = CartRepository.cartItems

    fun addToCart(product: Product) {
        CartRepository.addToCart(product)
    }

    fun removeFromCart(product: Product) {
        CartRepository.removeFromCart(product)
    }

    fun clearCart() {
        CartRepository.clearCart()
    }
}
