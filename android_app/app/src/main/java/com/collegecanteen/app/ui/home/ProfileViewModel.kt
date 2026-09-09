package com.collegecanteen.app.ui.home

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.collegecanteen.app.data.model.ProfileData
import com.collegecanteen.app.data.remote.RetrofitClient
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed class ProfileUiState {
    object Loading : ProfileUiState()
    data class Success(val profile: ProfileData) : ProfileUiState()
    data class Error(val message: String) : ProfileUiState()
}

class ProfileViewModel : ViewModel() {
    private val _uiState = MutableStateFlow<ProfileUiState>(ProfileUiState.Loading)
    val uiState: StateFlow<ProfileUiState> = _uiState.asStateFlow()

    private val _updateState = MutableStateFlow<Boolean?>(null)
    val updateState: StateFlow<Boolean?> = _updateState.asStateFlow()

    fun loadProfile(userId: Int, token: String) {
        viewModelScope.launch {
            _uiState.value = ProfileUiState.Loading
            try {
                val response = RetrofitClient.apiService.getProfile(userId, token)
                if (response.isSuccessful && response.body()?.success == true) {
                    val profile = response.body()?.data
                    if (profile != null) {
                        _uiState.value = ProfileUiState.Success(profile)
                    } else {
                        _uiState.value = ProfileUiState.Error("Profile data missing")
                    }
                } else {
                    _uiState.value = ProfileUiState.Error(response.body()?.message ?: "Failed to load profile")
                }
            } catch (e: Exception) {
                _uiState.value = ProfileUiState.Error(e.message ?: "Network error")
            }
        }
    }

    fun updateProfile(userId: Int, token: String, name: String, phone: String) {
        viewModelScope.launch {
            try {
                val response = RetrofitClient.apiService.updateProfile(userId, token, name, phone)
                _updateState.value = response.success
                if (response.success) {
                    // Reload profile
                    loadProfile(userId, token)
                }
            } catch (e: Exception) {
                _updateState.value = false
            }
        }
    }

    fun resetUpdateState() {
        _updateState.value = null
    }
}
