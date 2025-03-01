// Hamburger Menu Toggle
const hamburger = document.querySelector('.hamburger');
const navLinks = document.querySelector('.nav-links');

hamburger.addEventListener('click', () => {
    navLinks.classList.toggle('active');
});

// Smooth Scrolling
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelector(this.getAttribute('href')).scrollIntoView({
            behavior: 'smooth'
        });
    });
});

// Contact Form Validation
const contactForm = document.querySelector('.contact-form');
contactForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const name = contactForm.querySelector('input[type="text"]').value;
    const email = contactForm.querySelector('input[type="email"]').value;
    const message = contactForm.querySelector('textarea').value;

    if (name && email && message) {
        alert('Message sent successfully! We’ll get back to you soon.');
        contactForm.reset();
    } else {
        alert('Please fill in all fields.');
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const steps = document.querySelectorAll('.step');
    const formSteps = document.querySelectorAll('.form-step');
    let currentStep = 1;

    // Show the first step
    showStep(currentStep);

    // Next button click
    document.querySelectorAll('.next-btn').forEach(button => {
        button.addEventListener('click', function() {
            if (validateStep(currentStep)) {
                currentStep++;
                showStep(currentStep);
            }
        });
    });

    // Previous button click
    document.querySelectorAll('.prev-btn').forEach(button => {
        button.addEventListener('click', function() {
            currentStep--;
            showStep(currentStep);
        });
    });

    function showStep(step) {
        // Hide all steps
        formSteps.forEach(formStep => formStep.classList.remove('active'));
        steps.forEach(s => s.classList.remove('active'));

        // Show current step
        document.getElementById(`step-${step}`).classList.add('active');
        document.querySelector(`.step[data-step="${step}"]`).classList.add('active');

        // Disable/Enable buttons based on step
        if (step === 1) {
            document.querySelector('.prev-btn')?.classList.add('disabled');
        } else {
            document.querySelector('.prev-btn')?.classList.remove('disabled');
        }
        if (step === 3) {
            document.querySelector('.next-btn')?.style.display = 'none';
            document.querySelector('.submit-btn')?.style.display = 'block';
        } else {
            document.querySelector('.next-btn')?.style.display = 'block';
            document.querySelector('.submit-btn')?.style.display = 'none';
        }
    }

    function validateStep(step) {
        let isValid = true;
        const inputs = document.querySelectorAll(`#step-${step} input[required], #step-${step} select[required], #step-${step} textarea[required]`);
        
        inputs.forEach(input => {
            if (!input.value.trim()) {
                isValid = false;
                input.style.borderColor = 'red';
            } else {
                input.style.borderColor = '#ddd';
            }
        });

        // Additional validation for password match in step 2
        if (step === 2) {
            const password = document.querySelector('input[name="password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            if (password !== confirmPassword) {
                isValid = false;
                document.querySelector('input[name="confirm_password"]').style.borderColor = 'red';
                alert('Passwords do not match!');
            }
        }

        return isValid;
    }
});