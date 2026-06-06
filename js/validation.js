// Form validation for add student form
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('addStudentForm');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        const firstName = document.getElementById('first_name').value.trim();
        const lastName = document.getElementById('last_name').value.trim();
        const email = document.getElementById('email').value.trim();
        const phone = document.getElementById('phone').value.trim();
        const course = document.getElementById('course').value.trim();
        const admissionDate = document.getElementById('admission_date').value;
        const feeAmount = document.getElementById('fee_amount').value.trim();
        const studentId = document.getElementById('student_id').value.trim();

        let errors = [];

        if (firstName === '') {
            errors.push('First name is required.');
        }
        if (lastName === '') {
            errors.push('Last name is required.');
        }
        if (email === '') {
            errors.push('Email is required.');
        } else if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
            errors.push('Email is invalid.');
        }
        if (phone === '') {
            errors.push('Phone number is required.');
        }
        if (course === '') {
            errors.push('Course name is required.');
        }
        if (admissionDate === '') {
            errors.push('Admission date is required.');
        } else {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            const parts = admissionDate.split('-');
            const selectedDate = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
            if (selectedDate > tomorrow) {
                errors.push('Admission date cannot be more than one day in the future.');
            }
        }
        if (feeAmount === '') {
            errors.push('Fee amount is required.');
        } else if (isNaN(feeAmount) || parseFloat(feeAmount) < 0) {
            errors.push('Fee amount must be a valid non-negative number.');
        }
        if (studentId === '') {
            errors.push('Student ID is required.');
        } else if (!studentId.startsWith('YC')) {
            errors.push('Student ID must start with YC.');
        }

        if (errors.length > 0) {
            e.preventDefault();
            alert(errors.join('\n'));
        }
    });
});