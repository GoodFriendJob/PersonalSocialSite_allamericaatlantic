document.getElementById("loginForm").addEventListener("submit", async function(e) { 
    e.preventDefault(); 

    // 1. Gather form input values manually to build a clean JSON object
    // (Make sure the element IDs 'email' and 'password' match your HTML inputs)
    const email = document.getElementById("email").value;
    const password = document.getElementById("password").value;
    const data = { email, password };

    try {
        // 2. Send data to your PHP route as JSON
        const response = await fetch("login.php", { // Update path if your route is in /app/routes/
            method: "POST", 
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(data) 
        });

        const result = await response.json();

        // 3. Handle the response safely
        if (response.ok || result.success) { 
            // Redirect to UI page 
            window.location.href = "app.php"; 
        } else { 
            // Check if the HTML 'message' element exists before using it
            const messageElement = document.getElementById("message");
            const errorMessage = result.message || result.error || "Invalid username or password.";

            if (messageElement) {
                messageElement.innerText = errorMessage;
            } else {
                // Pop-up fallback if the HTML element is missing from the page
                alert(errorMessage); 
            }
        } 
    } catch (error) {
        alert("An error occurred during login. Please try again.");
        console.error(error);
    }
});
