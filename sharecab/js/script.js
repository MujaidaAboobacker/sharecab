// ShareCab - Client-side validation & interactivity

document.addEventListener("DOMContentLoaded", function () {

    /* ------------------------------
       Password Validation
    ------------------------------ */
    const password = document.getElementById("password");
    if (password) {
        password.addEventListener("input", function () {
            if (password.value.length > 0 && password.value.length < 6) {
                password.setCustomValidity("Password must be at least 6 characters long.");
            } else {
                password.setCustomValidity("");
            }
        });
    }

    /* ------------------------------
       Phone Number Validation (10 Digits)
    ------------------------------ */
    const phone = document.getElementById("phone");
    if (phone) {
        phone.addEventListener("input", function () {
            const phonePattern = /^[0-9]{10}$/;
            if (phone.value.length > 0 && !phonePattern.test(phone.value)) {
                phone.setCustomValidity("Please enter a valid 10-digit Indian phone number (e.g. 9876543210).");
            } else {
                phone.setCustomValidity("");
            }
        });
    }

    /* ------------------------------
       Prevent Past Ride Dates
    ------------------------------ */
    const rideDate = document.getElementById("ride_date");
    if (rideDate) {
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, "0");
        const day = String(today.getDate()).padStart(2, "0");
        const todayString = `${year}-${month}-${day}`;
        rideDate.min = todayString;

        // If field has no value, set default to today
        if (!rideDate.value) {
            rideDate.value = todayString;
        }
    }

    /* ------------------------------
       Dynamic Fare Per Person Splitter
    ------------------------------ */
    const fareInput = document.getElementById("fare");
    const seatsInput = document.getElementById("max_seats");
    const fareDisplay = document.getElementById("farePerPerson");

    function calculateFare() {
        if (!fareInput || !seatsInput || !fareDisplay) {
            return;
        }

        const fare = parseFloat(fareInput.value);
        const seats = parseInt(seatsInput.value);

        if (fare > 0 && seats > 0) {
            const farePerPerson = fare / seats;
            fareDisplay.innerHTML = `
                💵 <strong>Estimated Fare:</strong> ₹${farePerPerson.toFixed(2)} per passenger 
                <span style="font-size: 12px; opacity: 0.85;">(₹${fare.toFixed(2)} ÷ ${seats} seats)</span>
            `;
        } else {
            fareDisplay.innerHTML = `
                💡 Enter total fare & passenger seats to preview split per person
            `;
        }
    }

    if (fareInput) {
        fareInput.addEventListener("input", calculateFare);
    }

    if (seatsInput) {
        seatsInput.addEventListener("input", calculateFare);
    }

    // Trigger initial calculation if values exist
    calculateFare();

    /* ------------------------------
       Ride Form Submission Checks
    ------------------------------ */
    const rideForm = document.getElementById("rideForm");
    if (rideForm) {
        rideForm.addEventListener("submit", function (event) {
            if (fareInput && seatsInput) {
                const fare = parseFloat(fareInput.value);
                const seats = parseInt(seatsInput.value);

                if (fare <= 0 || seats <= 0) {
                    event.preventDefault();
                    alert("Total fare and seat count must be greater than zero.");
                }
            }
        });
    }

});