// function showForm(formId) {
//     document.querySelectorAll(".form-box")
//       .forEach(box => box.classList.remove("active"));
//     document.getElementById(formId).classList.add("active");
//   }
  
//   // On first load, show the appropriate form
//   document.addEventListener("DOMContentLoaded", function(){
//     // if neither is active by PHP, default to login
//     if (!document.querySelector(".form-box.active")) {
//       showForm('login-form');
//     }
//   });


function showForm(formId) {
  document.querySelectorAll(".form-box").forEach(box => box.classList.remove("active"));
  document.getElementById(formId).classList.add("active");
}

// On first load, show the appropriate form
document.addEventListener("DOMContentLoaded", function () {
  if (!document.querySelector(".form-box.active")) {
    showForm('login-form');
  }

  // Optional: if email link contains verification, auto-process it
  if (firebase.auth().isSignInWithEmailLink(window.location.href)) {
    const email = window.localStorage.getItem("emailForSignIn");
    if (email) {
      firebase.auth().signInWithEmailLink(email, window.location.href)
        .then(result => {
          alert("Email verified. You can now complete your registration.");
          document.getElementById("otpCode").style.display = "none";
          document.getElementById("verifyBtn").style.display = "none";
          document.getElementById("registerForm").submit();
        })
        .catch(error => {
          alert("Verification failed: " + error.message);
        });
    } else {
      alert("Email not found in local storage.");
    }
  }
});

// Send OTP to user's email
function sendOTP() {
  const email = document.getElementById("emailField").value.trim();

  if (!email.endsWith("@iitp.ac.in")) {
    alert("Please use your IITP email address.");
    return;
  }

  const actionCodeSettings = {
    url: window.location.href,
    handleCodeInApp: true
  };

  firebase.auth().sendSignInLinkToEmail(email, actionCodeSettings)
    .then(() => {
      window.localStorage.setItem("emailForSignIn", email);
      alert("OTP link sent to your IITP email. Please check your inbox and click the link.");
      document.getElementById("otpCode").style.display = "block";
      document.getElementById("verifyBtn").style.display = "block";
    })
    .catch(error => {
      alert("Failed to send OTP: " + error.message);
    });
}

// This function only exists to guide user; real verification happens via link click
function verifyOTP() {
  alert("Check your IITP email, click the OTP link, then this page will auto-complete registration.");
}
  