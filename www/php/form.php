<?php
mysqli_report(MYSQLI_REPORT_OFF);

define('ROOT_DIR', dirname(__DIR__));

header('Content-Type: application/json; charset=utf-8');

class ValidationException extends Exception
{
  public array $errors;

  public function __construct(array $errors)
  {
    parent::__construct('Validation error');
    $this->errors = $errors;
  }
}

class ServerException extends Exception {}

function sanitizeInput($input)
{
  return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
function loadConfig($envPath)
{
  if (!file_exists($envPath)) {
    throw new Exception('Config file not found: ' . $envPath);
  }

  $env = parse_ini_file($envPath);

  if (
    empty($env['DB_HOST']) ||
    empty($env['DB_NAME']) ||
    empty($env['DB_USER']) ||
    empty($env['DB_PASSWORD'])
  ) {
    throw new Exception('DB_HOST or DB_NAME or DB_USER or DB_PASSWORD not found in .env');
  }

  return [
    'dbHost' => $env['DB_HOST'],
    'dbName' => $env['DB_NAME'],
    'dbUser' => $env['DB_USER'],
    'dbPassword' => $env['DB_PASSWORD'],
  ];
}
function getData($data)
{
  $errors = [];
  $cleanedData = [];

  $firstName = $data['firstName'] ?? '';
  if (empty($firstName)) {
    $errors[] = 'Поле "Имя" обязательное';
  } elseif (mb_strlen($firstName) > 50) {
    $errors[] = 'Имя не должно превышать 50 символов';
  } else {
    $cleanedData['firstName'] = sanitizeInput($firstName);
  }

  $lastName = $data['lastName'] ?? '';
  if (empty($lastName)) {
    $errors[] = 'Поле "Фамилия" обязательное';
  } elseif (mb_strlen($lastName) > 50) {
    $errors[] = 'Фамилия не должна превышать 50 символов';
  } else {
    $cleanedData['lastName'] = sanitizeInput($lastName);
  }

  $patronymic = $data['patronymic'] ?? '';
  if (!empty($patronymic)) {
    if (mb_strlen($patronymic) > 50) {
      $errors[] = 'Отчество не должно превышать 50 символов';
    } else {
      $cleanedData['patronymic'] = sanitizeInput($patronymic);
    }
  } else {
    $cleanedData['patronymic'] = '';
  }

  $birthDate = $data['birthDate'] ?? '';
  if (empty($birthDate)) {
    $errors[] = 'Поле "Дата рождения" обязательное';
  } else {
    $dateParts = explode('-', $birthDate);
    if (count($dateParts) === 3 && checkdate($dateParts[1], $dateParts[2], $dateParts[0])) {
      $birthTimestamp = strtotime($birthDate);
      $minDate = strtotime('-120 years');
      $maxDate = strtotime('-18 years');
      if ($birthTimestamp < $minDate) {
        $errors[] = 'Дата рождения не может быть раньше ' . date('Y-m-d', $minDate);
      } elseif ($birthTimestamp > $maxDate) {
        $errors[] = 'Вам должно быть не менее 18 лет';
      } else {
        $cleanedData['birthDate'] = $birthDate;
      }
    } else {
      $errors[] = 'Некорректный формат даты рождения. Используйте ГГГГ-ММ-ДД';
    }
  }

  $email = trim($data['email'] ?? '');
  if (!empty($email)) {
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
          $errors[] = 'Некорректный email адрес';
      } elseif (mb_strlen($email) > 50) {
          $errors[] = 'Email не должен превышать 50 символов';
      } else {
          $cleanedData['email'] = sanitizeInput($email);
      }
  } else {
      $cleanedData['email'] = '';
  }

  $phone = $data['phone'] ?? '';
  if (empty($phone)) {
    $errors[] = 'Поле "Телефон" обязательное';
  } else {
    $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
    if (!str_starts_with($cleanPhone, '+375') && !str_starts_with($cleanPhone, '+7')) {
      $errors[] = 'Телефон должен начинаться с +375 или +7';
    } else {
      $expectedLength = str_starts_with($cleanPhone, '+375') ? 13 : 12;
      if (strlen($cleanPhone) !== $expectedLength) {
        $country = str_starts_with($cleanPhone, '+375') ? '+375' : '+7';
        $digits = str_starts_with($cleanPhone, '+375') ? 9 : 10;
        $errors[] = "Для $country требуется $digits цифр после кода";
      } elseif (mb_strlen($phone) > 50) {
        $errors[] = 'Телефон не должен превышать 50 символов';
      } else {
        $cleanedData['phone'] = $cleanPhone;
      }
    }
  }

  $maritalStatus = trim($data['maritalStatus'] ?? '');
  if (empty($maritalStatus)) {
    $errors[] = 'Поле "Семейное положение" обязательно';
  } elseif (mb_strlen($maritalStatus) > 30) {
    $errors[] = 'Семейное положение не должно превышать 30 символов';
  } else {
    $allowedStatuses = [
      'Kawaler/Panna',
      'Żonaty/Zamężna',
      'Rozwiedziony/Rozwiedziona',
      'Wdowiec/Wdowa',
    ];
    if (!in_array($maritalStatus, $allowedStatuses)) {
      $errors[] = 'Некорректное значение семейного положения';
    } else {
      $cleanedData['maritalStatus'] = sanitizeInput($maritalStatus);
    }
  }

  $about = trim($data['about'] ?? '');
  if (!empty($about)) {
    if (mb_strlen($about) > 1000) {
      $errors[] = 'Поле "О себе" не должно превышать 1000 символов';
    } else {
      $cleanedData['about'] = sanitizeInput($about);
    }
  } else {
    $cleanedData['about'] = '';
  }

  if (!empty($errors)) {
    throw new ValidationException($errors);
  }

  return $cleanedData;
}
function connectToDatabase($config)
{
  $conn = mysqli_connect(
    $config['dbHost'],
    $config['dbUser'],
    $config['dbPassword'],
    $config['dbName'],
  );

  if (!$conn) {
    error_log('MySQL Connection Error: ' . mysqli_connect_error());
    throw new ServerException('Ошибка подключения к базе данных');
  }

  return $conn;
}
function saveFormToDatabase($conn, $data)
{
  $sql = "INSERT INTO requests 
            (first_name, last_name, patronymic, email, phone, marital_status, about_request, birthdate) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

  $stmt = mysqli_prepare($conn, $sql);
  if (!$stmt) {
    return [
      'success' => false,
      'message' => 'Ошибка подготовки запроса: ' . mysqli_error($conn),
    ];
  }

  mysqli_stmt_bind_param(
    $stmt,
    'ssssssss',
    $data['firstName'],
    $data['lastName'],
    $data['patronymic'],
    $data['email'],
    $data['phone'],
    $data['maritalStatus'],
    $data['about'],
    $data['birthDate'],
  );

  if (!mysqli_stmt_execute($stmt)) {
    error_log('Execute failed: ' . mysqli_stmt_error($stmt));
    throw new ServerException('Ошибка сохранения данных');
  }

  $id = mysqli_stmt_insert_id($stmt);
  mysqli_stmt_close($stmt);

  return [
    'success' => true,
    'message' => 'Заявка успешно сохранена!',
    'id' => $id,
  ];
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
      'success' => false,
      'message' => 'Метод не поддерживается. Используйте POST.',
    ]);
    exit();
  }
  $config = loadConfig('../.env');
  $data = getData($_POST);
  $connection = connectToDatabase($config);
  if (!is_object($connection)) {
    echo json_encode($connection, JSON_UNESCAPED_UNICODE);
    exit();
  }
  $result = saveFormToDatabase($connection, $data);
  mysqli_close($connection);
  http_response_code(200);
  echo json_encode($result);
} catch (ValidationException $e) {
  http_response_code(422);
  echo json_encode(
    [
      'success' => false,
      'type' => 'Ошибка валидации',
      'message' => $e->errors,
    ],
    JSON_UNESCAPED_UNICODE,
  );
} catch (ServerException $e) {
  http_response_code(500);
  error_log($e->getMessage());
  echo json_encode([
    'success' => false,
    'message' => 'Ошибка сервера. Попробуйте позже',
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  error_log($e);
  echo json_encode([
    'success' => false,
    'message' => 'Непредвиденная ошибка',
  ]);
}
