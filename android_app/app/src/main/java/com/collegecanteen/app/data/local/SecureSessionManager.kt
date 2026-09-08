package com.collegecanteen.app.data.local

import android.content.Context
import android.content.SharedPreferences
import com.collegecanteen.app.data.model.User

class SecureSessionManager(context: Context) {
    private val prefs: SharedPreferences = context.getSharedPreferences("canteen_secure_prefs", Context.MODE_PRIVATE)

    fun saveSession(user: User) {
        prefs.edit().apply {
            putInt("USER_ID", user.id)
            putString("USER_NAME", user.name)
            putString("USER_EMAIL", user.email)
            putString("USER_PHONE", user.phone)
            putString("USER_TOKEN", user.token)
            apply()
        }
    }

    fun getToken(): String? {
        return prefs.getString("USER_TOKEN", null)
    }

    fun getUserId(): Int {
        return prefs.getInt("USER_ID", -1)
    }

    fun isLoggedIn(): Boolean {
        return getToken() != null
    }

    fun clearSession() {
        prefs.edit().clear().apply()
    }
}
