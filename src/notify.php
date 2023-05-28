<?php

require __DIR__.'/helpers.php';

/*
 * Получить список пользователей кому надо отправить уведомление.
 * Этапы работы скрипта:
 *  1) Получить из базы пользователей для которых необходимо отправить уведомления.
 *  2) Для еще не проверенных емейлов создать задачу на проверку.
 *  3) Создать задачу на отправку почты на емейл.
 */

// Запрос возвращает пользователей у которых подписка заканчивается через один или три дня
// и емейл был проверен и он валиден, либо еще не был проверен.
// Так же учитывается то что мы еще не отправили уведомление для этого емейла и он не находится в состоянии проверки.
const GET_USERS_QUERY = <<<SQL
select u.*
from users u
where u.confirmed
    and ((u.checked = true and u.valid = true) or u.checked = false)
    and (
            (u.validts between now() and now() + interval '1 day' and u.email not in (select n.email from notifications n where n.template = 'one_day_remaining' and n.created_at > u.validts - interval '1 month'))
            or
            (u.validts between now() + interval '2 days' and now() + interval '3 day' and u.email not in (select n.email from notifications n where n.template = 'three_days_remaining' and n.created_at > u.validts - interval '1 month'))
        )
    and u.email not in (select v.email from validations v where v.checked_at is null)
limit 1000
SQL;

// Запрос на добавление задания на проверку емейла пользователя.
const ADD_EMAIL_FOR_VALIDATION = <<<SQL
insert into validations (email, status, created_at, updated_at)
values (:email, 'created', 'now()', 'now()')
SQL;

// Запрос на добавление задания на отправку уведомления пользователю.
const ADD_EMAIL_FOR_NOTIFICATION = <<<SQL
insert into notifications (email, template, status, created_at, updated_at)
values (:email, :template, 'created', 'now()', 'now()')
SQL;


// Генератор для получения пользователей из запроса.
function users(): iterable
{
    $stmt = getDB()->query(GET_USERS_QUERY, \PDO::FETCH_ASSOC);
    while ($user = $stmt->fetch()) {
        yield $user;
    }
}

function checkEmails(array $emails): void
{
    $stmt = getCachedStmt(ADD_EMAIL_FOR_VALIDATION);

    getDB()->beginTransaction();

    foreach ($emails as $email) {
        $stmt->execute(['email' => $email]);
    }

    getDB()->commit();
}

function getNotificationTemplate(\DateTimeImmutable $validts): string
{
    $current = new \DateTimeImmutable();

    if ($validts < $current->add(new DateInterval('P1D'))) {
        return 'one_day_remaining';
    }

    if ($validts < $current->add(new DateInterval('P3D'))) {
        return 'three_days_remaining';
    }

    throw new \InvalidArgumentException('Not supported subscription type');
}

function sendNotifications(array $users): void
{
    $stmt = getCachedStmt(ADD_EMAIL_FOR_NOTIFICATION);

    getDB()->beginTransaction();

    foreach ($users as $user) {
        $stmt->execute([
            'email' => $user['email'],
            'template' => getNotificationTemplate(new \DateTimeImmutable($user['validts'])),
        ]);
    }

    getDB()->commit();
}

// Запрос возвращает записи батчами по 1000,
// в цикле обрабатываем пользователей для каждого батча, пока они не кончатся.
do {
    $processedUsersCount = 0;

    $notCheckedUsers = [];
    $sendNotifications = [];

    foreach (users() as $user) {
        $processedUsersCount++;

        // Пользователь еще не проверен, добавляем в список на проверку
        // и переходим к следующему.
        if ($user['checked'] === false) {
            $notCheckedUsers[] = $user['email']; // Для проверки нам нужен только email
            continue;
        }

        $sendNotifications[] = [
            'email' => $user['email'],
            'validts' => $user['validts'], // Срок подписки нам нужен для определения шаблона письма
        ];
    }

    checkEmails($notCheckedUsers);
    sendNotifications($sendNotifications);
} while ($processedUsersCount > 0);
