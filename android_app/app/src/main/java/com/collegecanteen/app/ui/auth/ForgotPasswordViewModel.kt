package com.collegecanteen.app.ui.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.collegecanteen.app.data.remote.RetrofitClient
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed class ForgotPasswordUiState {
    object Initial : ForgotPasswordUiState()
    object Loading : ForgotPasswordUiState()
    data class Success(val message: String, val step: Int) : ForgotPasswordUiState()
    data class Error(val message: String) : ForgotPasswordUiState()
}

class ForgotPasswordViewModel : ViewModel() {
    private val _uiState = MutableStateFlow<ForgotPasswordUiState>(ForgotPasswordUiState.Initial)
    val uiState: StateFlow<ForgotPasswordUiState> = _uiState.asStateFlow()

    private val api = RetrofitClient.apiService

    var currentStep = 1 // 1: Email, 2: OTP, 3: New Password
    var email: String = ""
    var otp: String = ""

    fun sendOtp(email: String) {
        viewModelScope.launch {
            _uiState.value = ForgotPasswordUiState.Loading
            try {
                this@ForgotPasswordViewModel.email = email
                val response = api.sendOtp(email)
                if (response.success) {
                    currentStep = 2
                    _uiState.value = ForgotPasswordUiState.Success(response.message, currentStep)
                } else {
                    _uiState.value = ForgotPasswordUiState.Error(response.message)
                }
            } catch (e: Exception) {
                _uiState.value = ForgotPasswordUiState.Error(e.message ?: "Network error")
            }
        }
    }

    fun verifyOtp(otp: String) {
        viewModelScope.launch {
            _uiState.value = ForgotPasswordUiState.Loading
            try {
                this@ForgotPasswordViewModel.otp = otp
                val response = api.verifyOtp(email, otp)
                if (response.success) {
                    currentStep = 3
                    _uiState.value = ForgotPasswordUiState.Success(response.message, currentStep)
                } else {
                    _uiState.value = ForgotPasswordUiState.Error(response.message)
                }
            } catch (e: Exception) {
                _uiState.value = ForgotPasswordUiState.Error(e.message ?: "Network error")
            }
        }
    }

    fun resetPassword(password: String) {
        viewModelScope.launch {
            _uiState.value = ForgotPasswordUiState.Loading
            try {
                val response = api.resetPassword(email, otp, password)
                if (response.success) {
                    currentStep = 4 // Done
                    _uiState.value = ForgotPasswordUiState.Success(response.message, currentStep)
                } else {
                    _uiState.value = ForgotPasswordUiState.Error(response.message)
                }
            } catch (e: Exception) {
                _uiState.value = ForgotPasswordUiState.Error(e.message ?: "Network error")
            }
        }
    }

    fun resetState() {
        _uiState.value = ForgotPasswordUiState.Initial
    }
}
