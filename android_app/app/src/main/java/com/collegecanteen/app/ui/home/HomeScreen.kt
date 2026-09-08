package com.collegecanteen.app.ui.home

import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeScreen(
    onNavigateToMenu: () -> Unit,
    onNavigateToCart: () -> Unit,
    onNavigateToOrders: () -> Unit,
    onNavigateToProfile: () -> Unit
) {
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("College Canteen") },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.primary,
                    titleContentColor = MaterialTheme.colorScheme.onPrimary
                )
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .padding(16.dp),
            verticalArrangement = Arrangement.Center,
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text(
                text = "Welcome to College Canteen!",
                style = MaterialTheme.typography.headlineMedium
            )
            Spacer(modifier = Modifier.height(32.dp))
            
            Button(
                onClick = onNavigateToMenu,
                modifier = Modifier.fillMaxWidth().height(50.dp)
            ) {
                Text("View Menu")
            }
            Spacer(modifier = Modifier.height(16.dp))
            
            Button(
                onClick = onNavigateToCart,
                modifier = Modifier.fillMaxWidth().height(50.dp)
            ) {
                Text("View Cart")
            }
            Spacer(modifier = Modifier.height(16.dp))
            
            Button(
                onClick = onNavigateToOrders,
                modifier = Modifier.fillMaxWidth().height(50.dp)
            ) {
                Text("My Orders")
            }
            Spacer(modifier = Modifier.height(16.dp))
            
            OutlinedButton(
                onClick = onNavigateToProfile,
                modifier = Modifier.fillMaxWidth().height(50.dp)
            ) {
                Text("Profile")
            }
        }
    }
}
