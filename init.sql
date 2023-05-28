create table if not exists users
(
    username  text primary key not null,
    email     text unique      not null,
    validts   timestamp,
    confirmed bool             not null default false,
    checked   bool             not null default false,
    valid     bool             not null default false
);

create table if not exists notifications
(
    id         bigserial primary key,
    email      text      not null references users (email),
    template   text      not null,
    status     text      not null,
    sent_at    timestamp,
    created_at timestamp not null,
    updated_at timestamp not null
);
create index if not exists notifications_template_created_at_idx on notifications (template, created_at);

create table if not exists validations
(
    id         bigserial primary key,
    email      text      not null references users (email),
    status     text      not null,
    checked_at timestamp,
    created_at timestamp not null,
    updated_at timestamp not null
);
create index if not exists validations_checked_at_idx on validations (checked_at) where checked_at is null;

truncate table notifications;
truncate table validations;
truncate table users cascade;

insert into users (username, email, validts, confirmed)
select concat('user', n)                                  as username,
       concat('user', n, '@example.com')                  as email,
       case
           when random() > 0.8 then (now() - interval '4 days') + random() * interval '8 days'
           end                                            as validts,
       case when random() > 0.75 then true else false end as confirmed
from generate_series(1, 5000000) as n;