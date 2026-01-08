class FormsValidation {
  selectors = {
    form: '[data-js-form]',
    fieldErrors: '[data-js-form-field-errors]',
    formResult: '[data-js-info-block]',
  };
  errorMessages = {
    valueMissing: () => 'Пожалуйста, заполните это поле',
    patternMismatch: ({ title }) => title || 'Данные не в правильном формате',
    tooShort: ({ minLength }) =>
      `Значение слишком короткое, минимальное количество символов — ${minLength}`,
    tooLong: ({ maxLength }) =>
      `Значение слишком длинное, максимальное количество символов — ${maxLength}`,
    typeMismatch: ({ type }) => {
      switch (type) {
        case 'email':
          return 'Пожалуйста, введите правильный email адрес';
        case 'url':
          return 'Пожалуйста, введите правильный URL адрес';
        default:
          return 'Неверный формат данных';
      }
    },
    rangeOverflow: ({ max }) => `Значение не может быть больше ${max}`,
    rangeUnderflow: ({ min }) => `Значение не может быть меньше ${min}`,
    stepMismatch: ({ value, step }) => `Значение должно быть кратно ${step}`,
    badInput: () => 'Неверное значение',
    customError: ({ message }) => message || 'Ошибка валидации',
  };

  constructor() {
    this.bindEvents();
  }

  manageErrors(fieldControlElement, errorMessages) {
    const fieldErrorsElement = fieldControlElement.parentElement.querySelector(
      this.selectors.fieldErrors,
    );

    fieldErrorsElement.innerHTML = errorMessages
      .map((message) => `<span class="error-text">${message}</span>`)
      .join('');

    if (errorMessages.length > 0) {
      fieldControlElement.classList.add('input-error');
    } else {
      fieldControlElement.classList.remove('input-error');
    }
  }

  validateBirthDate(field) {
    if (!field.value) return;

    const birthDate = new Date(field.value);
    const today = new Date();

    let age = today.getFullYear() - birthDate.getFullYear();
    const m = today.getMonth() - birthDate.getMonth();

    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
      age--;
    }

    if (birthDate > today) {
      field.setCustomValidity('Дата рождения не может быть в будущем');
      return;
    }

    if (age < 18) {
      field.setCustomValidity('Вам должно быть не менее 18 лет');
    } else {
      field.setCustomValidity('');
    }
  }

  validateField(fieldControlElement) {
    if (fieldControlElement.name === 'birthDate') {
      this.validateBirthDate(fieldControlElement);
    }

    const errors = fieldControlElement.validity;
    const errorMessages = [];

    Object.entries(this.errorMessages).forEach(
      ([errorType, getErrorMessage]) => {
        if (errors[errorType]) {
          errorMessages.push(getErrorMessage(fieldControlElement));
        }
      },
    );

    this.manageErrors(fieldControlElement, errorMessages);

    const isValid = errorMessages.length === 0;

    fieldControlElement.ariaInvalid = !isValid;

    return isValid;
  }

  onBlur(event) {
    const { target } = event;
    const isFormField = target.closest(this.selectors.form);
    const isRequired = target.required;

    if (isFormField && isRequired) {
      this.validateField(target);
    }
  }

  onChange(event) {
    const { target } = event;

    if (!target.required) return;

    const isToggleType = ['radio', 'checkbox'].includes(target.type);
    const isSelect = target.tagName === 'SELECT';

    if (isToggleType || isSelect) {
      this.validateField(target);
      return;
    }

    if (target.value.trim() !== '') {
      this.validateField(target);
    }
  }

  showResultMessage(message, type = 'success') {
    const resultElement = document.querySelector(this.selectors.formResult);
    if (resultElement) {
      resultElement.textContent = message;
      resultElement.className = `info-block ${type}`;
      resultElement.style.display = 'block';

      setTimeout(() => {
        resultElement.style.display = 'none';
      }, 5000);
    }
  }

  async submitForm(form) {
    const formData = new FormData(form);

    console.log(form.action);

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
          Accept: 'application/json',
        },
      });

      const result = await response.json();

      if (result.success) {
        this.showResultMessage('Форма успешно отправлена!', 'success');
        form.reset();
      } else {
        this.showResultMessage(
          result.message || 'Ошибка при отправке формы',
          'error',
        );
      }
    } catch (error) {
      console.error('Ошибка:', error);
      this.showResultMessage('Ошибка соединения с сервером', 'error');
    }
  }

  onSubmit(event) {
    event.preventDefault();

    const isFormElement = event.target.matches(this.selectors.form);
    const form = event.target;

    if (!isFormElement) {
      return;
    }

    const requiredControlElements = [...event.target.elements].filter(
      ({ required }) => required,
    );
    let isFormValid = true;
    let firstInvalidFieldControl = null;

    requiredControlElements.forEach((element) => {
      const isFieldValid = this.validateField(element);

      if (!isFieldValid) {
        isFormValid = false;

        if (!firstInvalidFieldControl) {
          firstInvalidFieldControl = element;
        }
      }
    });

    if (!isFormValid) {
      firstInvalidFieldControl.focus();
      return;
    }

    this.submitForm(form);
  }

  bindEvents() {
    document.addEventListener(
      'blur',
      (event) => {
        this.onBlur(event);
      },
      { capture: true },
    );
    document.addEventListener('change', (event) => this.onChange(event));
    document.addEventListener('submit', (event) => this.onSubmit(event));
  }
}

new FormsValidation();
