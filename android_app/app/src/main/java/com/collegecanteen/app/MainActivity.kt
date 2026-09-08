package com.collegecanteen.app

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import com.collegecanteen.app.data.local.SecureSessionManager
import com.collegecanteen.app.data.remote.RetrofitClient
import com.collegecanteen.app.data.repository.AuthRepository
import com.collegecanteen.app.ui.auth.LoginScreen
import com.collegecanteen.app.ui.auth.LoginViewModel
import com.collegecanteen.app.ui.auth.RegisterScreen
import com.collegecanteen.app.ui.theme.CollegeCanteenTheme
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        val sessionManager = SecureSessionManager(this)
        val authRepository = AuthRepository(RetrofitClient.apiService, sessionManager)
        
        // Manual Factory for ViewModel
        val factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                if (modelClass.isAssignableFrom(LoginViewModel::class.java)) {
                    @Suppress("UNCHECKED_CAST")
                    return LoginViewModel(authRepository) as T
                }
                throw IllegalArgumentException("Unknown ViewModel class")
            }
        }

        setContent {
            CollegeCanteenTheme {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background
                ) {
                    val navController = rememberNavController()
                    val loginViewModel: LoginViewModel = viewModel(factory = factory)
                    
                    val startDest = if (sessionManager.isLoggedIn()) "home" else "login"

                    NavHost(navController = navController, startDestination = startDest) {
                        composable("login") {
                            LoginScreen(
                                viewModel = loginViewModel,
                                onNavigateToHome = {
                                    navController.navigate("home") {
                                        popUpTo("login") { inclusive = true }
                                    }
                                },
                                onNavigateToRegister = { navController.navigate("register") }
                            )
                        }
                        composable("register") {
                            RegisterScreen(
                                viewModel = loginViewModel,
                                onNavigateToHome = {
                                    navController.navigate("home") {
                                        popUpTo("login") { inclusive = true }
                                    }
                                },
                                onNavigateToLogin = { navController.popBackStack() }
                            )
                        }
                        composable("home") {
                            com.collegecanteen.app.ui.home.HomeScreen(
                                onNavigateToMenu = { navController.navigate("menu") },
                                onNavigateToCart = { navController.navigate("cart") },
                                onNavigateToOrders = { navController.navigate("orders") },
                                onNavigateToProfile = { /* TODO */ }
                            )
                        }
                        composable("menu") {
                            com.collegecanteen.app.ui.menu.MenuScreen(
                                onNavigateBack = { navController.popBackStack() }
                            )
                        }
                        composable("cart") {
                            com.collegecanteen.app.ui.cart.CartScreen(
                                onNavigateBack = { navController.popBackStack() },
                                onNavigateToCheckout = { navController.navigate("checkout") }
                            )
                        }
                        composable("checkout") {
                            val userId = sessionManager.getUserId()
                            val token = sessionManager.getToken() ?: ""
                            com.collegecanteen.app.ui.checkout.CheckoutScreen(
                                userId = userId,
                                token = token,
                                onNavigateBack = { navController.popBackStack() },
                                onOrderSuccess = {
                                    navController.navigate("orders") {
                                        popUpTo("home") { inclusive = false }
                                    }
                                }
                            )
                        }
                        composable("orders") {
                            val userId = sessionManager.getUserId()
                            val token = sessionManager.getToken() ?: ""
                            com.collegecanteen.app.ui.orders.OrdersScreen(
                                userId = userId,
                                token = token,
                                onNavigateBack = { navController.popBackStack() }
                            )
                        }
                    }
                }
            }
        }
    }
}
