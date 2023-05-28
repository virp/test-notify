<?php

require __DIR__.'/helpers.php';

/*
 * Получить список задач на отправку уведомлений и выполнить отправку почты.
 * Данный скрипт можно запускать в несколько потоков для ускорения обработки задач.
 *
 * Этапы работы скрипта:
 *  1) Получить из базы уведомление для отправки.
 *  2) Изменить статус задачи на "sending"
 *  3) Выполнить отправку емейла.
 *  4) Изменить статус задачи на "sent".
 */

// Запрос возвращает емейл на который необходимо отправить уведомление.
const GET_NEW_NOTIFICATION_TASK = <<<SQL
select n.id, n.email, n.template, u.username
from notifications n
         join users u on u.email = n.email
where n.status = 'created'
order by n.created_at
limit 1 for update of n skip locked
SQL;

// Запрос изменяет статус задачи на "sending".
const CHANGE_TASK_STATUS_TO_SENDING = <<<SQL
update notifications set status = 'sending', updated_at = now() where id = :id
SQL;

// Запрос изменяет статус задачи на "sent".
const CHANGE_TASK_STATUS_TO_SENT = <<<SQL
update notifications set status = 'sent', sent_at = now(), updated_at = now() where id = :id
SQL;

// Функция отправки емейла.
function send_email(string $from, string $to, $text): void
{
    sleep(random_int(1, 10)); // Эмуляция работы отправки почты
}

function setTaskStatusSending(int $id): void
{
    $stmt = getCachedStmt(CHANGE_TASK_STATUS_TO_SENDING);

    $stmt->execute(['id' => $id]);
}

function setTaskStatusSent(int $id): void
{
    $stmt = getCachedStmt(CHANGE_TASK_STATUS_TO_SENT);

    $stmt->execute(['id' => $id]);
}

function getTask(): ?array
{
    getDB()->beginTransaction();

    $stmt = getDB()->query(GET_NEW_NOTIFICATION_TASK, \PDO::FETCH_ASSOC);
    $task = $stmt->fetch();

    if (!$task) {
        getDB()->rollBack();

        return null;
    }

    setTaskStatusSending($task['id']);

    getDB()->commit();

    return $task;
}

function getEmailMessage(string $template, array $payload): string
{
    return match ($template) {
        'one_day_remaining' => str_replace(
            '{username}',
            $payload['username'],
            '{username}, your subscription is expiring in one day',
        ),
        'three_days_remaining' => str_replace(
            '{username}',
            $payload['username'],
            '{username}, your subscription is expiring in three days',
        ),
        default => throw new \InvalidArgumentException('Not supported email templates')
    };
}

do {
    $task = getTask();

    if (!$task) {
        return;
    }

    $message = getEmailMessage($task['template'], ['username' => $task['username']]);

    send_email('noreply@example.com', $task['email'], $message);

    getDB()->beginTransaction();

    setTaskStatusSent($task['id']);

    getDB()->commit();
} while (true);