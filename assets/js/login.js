document.getElementById("loginForm").addEventListener("submit", async function (e) {
    e.preventDefault();

    const email = document.getElementById("email").value;
    const password = document.getElementById("password").value;
    const messageElement = document.getElementById("message");

    function showError(text) {
        if (messageElement) {
            messageElement.textContent = text;
            messageElement.style.display = "block";
        } else {
            alert(text);
        }
    }

    if (messageElement) {
        messageElement.style.display = "none";
    }

    try {
        // All API calls go through the JSON front controller, not a standalone
        // login.php — that file has never existed and the 404 HTML it returned
        // is what made every login attempt look like a network failure.
        const response = await fetch("app/index.php?route=auth/login", {
            method: "POST",
            credentials: "same-origin", // keep the PHP session cookie
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ email, password })
        });

        const text = await response.text();
        let result;
        try {
            result = text ? JSON.parse(text) : {};
        } catch (parseError) {
            // A PHP warning or HTML error page leaking into the body.
            console.error("Non-JSON login response:", text.slice(0, 300));
            showError("Server error. Please try again.");
            return;
        }

        if (response.ok && result.success) {
            window.location.href = "app.php";
            return;
        }

        showError(result.error || result.message || "Invalid username or password.");
    } catch (error) {
        showError("An error occurred during login. Please try again.");
        console.error(error);
    }
});
