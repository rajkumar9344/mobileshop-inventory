// Shared validation functions for phone and email fields and banking fields
function validatePhone(input, errorId = 'phone-error') {
    // UAE contact number: allow only digits and an optional leading '+', max 15 chars
    let v = input.value.replace(/[^0-9+]/g, '');
    v = v.replace(/(?!^)\+/g, ''); // '+' is allowed only as the first character
    input.value = v.slice(0, 15);

    const digits = input.value.replace(/\D/g, '');
    if (input.value.length > 0 && digits.length < 7) {
        showError(errorId, 'Enter a valid phone number (numbers and + only)');
    } else {
        hideError(errorId);
    }
}

function validateEmail(input, errorId = 'email-error') {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (input.value && !emailRegex.test(input.value)) {
        showError(errorId, 'Please enter a valid email address');
    } else {
        hideError(errorId);
    }
}

function validateTRN(input, errorId = 'trn-error') {
    // UAE TRN: digits only, exactly 15 characters
    input.value = input.value.replace(/[^0-9]/g, '').slice(0, 15);

    if (input.value.length === 0) {
        hideError(errorId);
        return;
    }

    if (input.value.length !== 15) {
        showError(errorId, 'TRN must be exactly 15 digits (e.g. 100123456700003)');
    } else {
        hideError(errorId);
    }
}

// Helper functions to show/hide error text (reduces duplicated DOM code)
function showError(errorId, message) {
    const el = document.getElementById(errorId);
    if (!el) return;
    el.textContent = message;
    el.style.display = 'block';
}

function hideError(errorId) {
    const el = document.getElementById(errorId);
    if (!el) return;
    el.style.display = 'none';
}

function validateBankName(input, errorId = 'bank-name-error') {
    // Trim and limit to 100 chars
    input.value = input.value.replace(/\s+/g, ' ').trim().slice(0, 100);

    if (input.value.length === 0) {
        hideError(errorId);
        return;
    }

    if (input.value.length < 2) {
        showError(errorId, 'Bank name must be at least 2 characters');
    } else {
        hideError(errorId);
    }
}

function validateBankAccount(input, errorId = 'bank-account-error') {
    // Allow digits, spaces and - / and limit length
    input.value = input.value.replace(/[^0-9\-\/\s]/g, '').slice(0, 50);

    const cleaned = input.value.replace(/\s+/g, '');

    if (cleaned.length === 0) {
        hideError(errorId);
        return;
    }

    if (cleaned.length < 6) {
        showError(errorId, 'Account number seems too short');
    } else {
        hideError(errorId);
    }
}

function validateBankBranch(input, errorId = 'bank-branch-error') {
    input.value = input.value.replace(/\s+/g, ' ').trim().slice(0, 100);

    if (input.value.length === 0) {
        hideError(errorId);
        return;
    }

    if (input.value.length < 2) {
        showError(errorId, 'Branch name must be at least 2 characters');
    } else {
        hideError(errorId);
    }
}

function validateIFSC(input, errorId = 'bank-ifsc-error') {
    // IFSC: 4 letters, 0, 6 alphanumeric (common Indian format)
    input.value = input.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase().slice(0, 11);

    if (input.value.length === 0) {
        hideError(errorId);
        return;
    }

    const ifscRegex = /^[A-Z]{4}0[A-Z0-9]{6}$/;
    if (!ifscRegex.test(input.value)) {
        showError(errorId, 'Please enter a valid IFSC (e.g. ABCD0E12345)');
    } else {
        hideError(errorId);
    }
}