<?php

require __DIR__.'/helpers.php';

/*
 * Получить список задач на проверку емейлов и выполнить их проверку.
 * Данный скрипт можно запускать в несколько потоков для ускорения обработки задач.
 *
 * Этапы работы скрипта:
 *  1) Получить из базы емейл для проверки.
 *  2) Изменить статус задачи на "checking"
 *  3) Выполнить проверку.
 *  4) Изменить статус задачи на "checked".
 *  5) Обновить запись пользователя в соответсвии с результатом.
 */

// Запрос возвращает емейл который необходимо проверить и блокирует запись для обновления статуса.
const GET_NEW_CHECK_TASK = <<<SQL
select v.id, v.email
from validations v
where v.status = 'created'
order by v.created_at
limit 1 for update skip locked
SQL;

// Запрос изменяет статус задачи на "checking".
const CHANGE_TASK_STATUS_TO_CHECKING = <<<SQL
update validations set status = 'checking', updated_at = now() where id = :id
SQL;

// Запрос изменяет статус задачи на "checked".
const CHANGE_TASK_STATUS_TO_CHECKED = <<<SQL
update validations set status = 'checked', checked_at = now(), updated_at = now() where id = :id
SQL;

// Запрос обновляет запись пользователя в соответсвии с результатом проверки.
const UPDATE_USER_EMAIL_CHECK_RESULT = <<<SQL
update users set checked = true, valid = :result where email = :email
SQL;

// Функция проверки емейл на валидность
function check_email(string $email): bool
{
    sleep(random_int(1, 60)); // Эмуляция работы внешней проверки

    return random_int(0, 1); // Случайный результат проверки
}

function setTaskStatusChecking(int $id): void
{
    $stmt = getCachedStmt(CHANGE_TASK_STATUS_TO_CHECKING);

    $stmt->execute(['id' => $id]);
}

function setTaskStatusChecked(int $id): void
{
    $stmt = getCachedStmt(CHANGE_TASK_STATUS_TO_CHECKED);

    $stmt->execute(['id' => $id]);
}

function updateUserEmailCheck(string $email, bool $result): void
{
    $stmt = getCachedStmt(UPDATE_USER_EMAIL_CHECK_RESULT);

    $stmt->execute(['email' => $email, 'result' => $result ? 1 : 0]);
}

function getTask(): ?array
{
    getDB()->beginTransaction();

    $stmt = getDB()->query(GET_NEW_CHECK_TASK, \PDO::FETCH_ASSOC);
    $task = $stmt->fetch();

    if (!$task) {
        getDB()->rollBack();

        return null;
    }

    setTaskStatusChecking($task['id']);

    getDB()->commit();

    return $task;
}

do {
    $task = getTask();

    if (!$task) {
        return;
    }

    $result = check_email($task['email']);

    getDB()->beginTransaction();

    setTaskStatusChecked($task['id']);
    updateUserEmailCheck($task['email'], $result);

    getDB()->commit();
} while (true);