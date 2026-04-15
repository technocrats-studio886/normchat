--
-- PostgreSQL database dump
--

\restrict v3xxq9GWm165cv305TSqhtbbdYcHshz5QfOcbAF8NmyQSEmudKv78cCWPOz1Lc5

-- Dumped from database version 16.13
-- Dumped by pg_dump version 16.13

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: ai_connections; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.ai_connections (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    provider character varying(255) NOT NULL,
    access_token text NOT NULL,
    refresh_token text,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.ai_connections OWNER TO normchat;

--
-- Name: ai_connections_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.ai_connections_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ai_connections_id_seq OWNER TO normchat;

--
-- Name: ai_connections_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.ai_connections_id_seq OWNED BY public.ai_connections.id;


--
-- Name: approvals; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.approvals (
    id bigint NOT NULL,
    group_id bigint NOT NULL,
    user_id bigint NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    requested_at timestamp(0) without time zone NOT NULL,
    approved_by bigint,
    rejected_by bigint,
    note text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.approvals OWNER TO normchat;

--
-- Name: approvals_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.approvals_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.approvals_id_seq OWNER TO normchat;

--
-- Name: approvals_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.approvals_id_seq OWNED BY public.approvals.id;


--
-- Name: audit_logs; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.audit_logs (
    id bigint NOT NULL,
    group_id bigint,
    actor_id bigint,
    action character varying(255) NOT NULL,
    target_type character varying(255),
    target_id bigint,
    metadata_json json,
    created_at timestamp(0) without time zone NOT NULL
);


ALTER TABLE public.audit_logs OWNER TO normchat;

--
-- Name: audit_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.audit_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.audit_logs_id_seq OWNER TO normchat;

--
-- Name: audit_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.audit_logs_id_seq OWNED BY public.audit_logs.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


ALTER TABLE public.cache OWNER TO normchat;

--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


ALTER TABLE public.cache_locks OWNER TO normchat;

--
-- Name: chat_message_queues; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.chat_message_queues (
    id bigint NOT NULL,
    group_id bigint NOT NULL,
    message_id bigint NOT NULL,
    status character varying(255) DEFAULT 'queued'::character varying NOT NULL,
    queued_at timestamp(0) without time zone,
    processed_at timestamp(0) without time zone,
    error_message character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.chat_message_queues OWNER TO normchat;

--
-- Name: chat_message_queues_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.chat_message_queues_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.chat_message_queues_id_seq OWNER TO normchat;

--
-- Name: chat_message_queues_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.chat_message_queues_id_seq OWNED BY public.chat_message_queues.id;


--
-- Name: exports; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.exports (
    id bigint NOT NULL,
    group_id bigint NOT NULL,
    file_name character varying(255) NOT NULL,
    storage_path character varying(255) NOT NULL,
    file_type character varying(255) NOT NULL,
    status character varying(255) DEFAULT 'queued'::character varying NOT NULL,
    created_by bigint NOT NULL,
    created_at timestamp(0) without time zone NOT NULL
);


ALTER TABLE public.exports OWNER TO normchat;

--
-- Name: exports_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.exports_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.exports_id_seq OWNER TO normchat;

--
-- Name: exports_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.exports_id_seq OWNED BY public.exports.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.failed_jobs OWNER TO normchat;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.failed_jobs_id_seq OWNER TO normchat;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: group_backups; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.group_backups (
    id bigint NOT NULL,
    group_id bigint NOT NULL,
    backup_type character varying(255) NOT NULL,
    storage_path character varying(255) NOT NULL,
    created_by bigint NOT NULL,
    created_at timestamp(0) without time zone NOT NULL
);


ALTER TABLE public.group_backups OWNER TO normchat;

--
-- Name: group_backups_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.group_backups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.group_backups_id_seq OWNER TO normchat;

--
-- Name: group_backups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.group_backups_id_seq OWNED BY public.group_backups.id;


--
-- Name: group_members; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.group_members (
    id bigint NOT NULL,
    group_id bigint NOT NULL,
    user_id bigint NOT NULL,
    role_id bigint NOT NULL,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    invited_by bigint,
    approved_by bigint,
    joined_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    last_read_message_id bigint,
    last_read_at timestamp(0) without time zone
);


ALTER TABLE public.group_members OWNER TO normchat;

--
-- Name: group_members_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.group_members_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.group_members_id_seq OWNER TO normchat;

--
-- Name: group_members_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.group_members_id_seq OWNED BY public.group_members.id;


--
-- Name: group_token_contributions; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.group_token_contributions (
    id bigint NOT NULL,
    group_id bigint NOT NULL,
    user_id bigint NOT NULL,
    source character varying(255) NOT NULL,
    token_amount bigint NOT NULL,
    price_paid integer DEFAULT 0 NOT NULL,
    payment_reference character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.group_token_contributions OWNER TO normchat;

--
-- Name: group_token_contributions_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.group_token_contributions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.group_token_contributions_id_seq OWNER TO normchat;

--
-- Name: group_token_contributions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.group_token_contributions_id_seq OWNED BY public.group_token_contributions.id;


--
-- Name: group_tokens; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.group_tokens (
    id bigint NOT NULL,
    group_id bigint NOT NULL,
    total_tokens bigint DEFAULT '0'::bigint NOT NULL,
    used_tokens bigint DEFAULT '0'::bigint NOT NULL,
    remaining_tokens bigint DEFAULT '0'::bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.group_tokens OWNER TO normchat;

--
-- Name: group_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.group_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.group_tokens_id_seq OWNER TO normchat;

--
-- Name: group_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.group_tokens_id_seq OWNED BY public.group_tokens.id;


--
-- Name: groups; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.groups (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    owner_id bigint NOT NULL,
    password_hash character varying(255),
    approval_enabled boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    share_id character varying(8),
    ai_provider character varying(255),
    ai_model character varying(255),
    ai_persona_style text,
    ai_persona_guardrails text,
    status character varying(30) DEFAULT 'active'::character varying NOT NULL
);


ALTER TABLE public.groups OWNER TO normchat;

--
-- Name: groups_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.groups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.groups_id_seq OWNER TO normchat;

--
-- Name: groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.groups_id_seq OWNED BY public.groups.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


ALTER TABLE public.job_batches OWNER TO normchat;

--
-- Name: jobs; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


ALTER TABLE public.jobs OWNER TO normchat;

--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.jobs_id_seq OWNER TO normchat;

--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: message_versions; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.message_versions (
    id bigint NOT NULL,
    message_id bigint NOT NULL,
    version_number integer DEFAULT 1 NOT NULL,
    content_snapshot text NOT NULL,
    edited_by bigint,
    edited_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.message_versions OWNER TO normchat;

--
-- Name: message_versions_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.message_versions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.message_versions_id_seq OWNER TO normchat;

--
-- Name: message_versions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.message_versions_id_seq OWNED BY public.message_versions.id;


--
-- Name: messages; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.messages (
    id bigint NOT NULL,
    group_id bigint NOT NULL,
    sender_type character varying(255) NOT NULL,
    sender_id bigint,
    content text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    message_type character varying(255) DEFAULT 'text'::character varying NOT NULL,
    attachment_disk character varying(255),
    attachment_path character varying(255),
    attachment_mime character varying(255),
    attachment_original_name character varying(255),
    attachment_size bigint,
    reply_to_message_id bigint
);


ALTER TABLE public.messages OWNER TO normchat;

--
-- Name: messages_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.messages_id_seq OWNER TO normchat;

--
-- Name: messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.messages_id_seq OWNED BY public.messages.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


ALTER TABLE public.migrations OWNER TO normchat;

--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.migrations_id_seq OWNER TO normchat;

--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


ALTER TABLE public.password_reset_tokens OWNER TO normchat;

--
-- Name: pending_payments; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.pending_payments (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    group_id bigint,
    order_id character varying(40) NOT NULL,
    payment_type character varying(30) NOT NULL,
    expected_amount integer NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    metadata_json json,
    paid_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.pending_payments OWNER TO normchat;

--
-- Name: pending_payments_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.pending_payments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pending_payments_id_seq OWNER TO normchat;

--
-- Name: pending_payments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.pending_payments_id_seq OWNED BY public.pending_payments.id;


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.permissions (
    id bigint NOT NULL,
    key character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.permissions OWNER TO normchat;

--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.permissions_id_seq OWNER TO normchat;

--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: poll_votes; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.poll_votes (
    id bigint NOT NULL,
    group_id bigint NOT NULL,
    poll_message_id bigint NOT NULL,
    user_id bigint NOT NULL,
    option_number smallint NOT NULL,
    voted_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.poll_votes OWNER TO normchat;

--
-- Name: poll_votes_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.poll_votes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.poll_votes_id_seq OWNER TO normchat;

--
-- Name: poll_votes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.poll_votes_id_seq OWNED BY public.poll_votes.id;


--
-- Name: recovery_logs; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.recovery_logs (
    id bigint NOT NULL,
    group_id bigint NOT NULL,
    backup_id bigint NOT NULL,
    restored_by bigint NOT NULL,
    restored_at timestamp(0) without time zone NOT NULL,
    reason text
);


ALTER TABLE public.recovery_logs OWNER TO normchat;

--
-- Name: recovery_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.recovery_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.recovery_logs_id_seq OWNER TO normchat;

--
-- Name: recovery_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.recovery_logs_id_seq OWNED BY public.recovery_logs.id;


--
-- Name: role_permissions; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.role_permissions (
    id bigint NOT NULL,
    role_id bigint NOT NULL,
    permission_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.role_permissions OWNER TO normchat;

--
-- Name: role_permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.role_permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.role_permissions_id_seq OWNER TO normchat;

--
-- Name: role_permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.role_permissions_id_seq OWNED BY public.role_permissions.id;


--
-- Name: roles; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    key character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.roles OWNER TO normchat;

--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.roles_id_seq OWNER TO normchat;

--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


ALTER TABLE public.sessions OWNER TO normchat;

--
-- Name: subscription_payments; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.subscription_payments (
    id bigint NOT NULL,
    subscription_id bigint NOT NULL,
    group_id bigint NOT NULL,
    created_by bigint NOT NULL,
    payment_type character varying(255) DEFAULT 'add_seat_dummy'::character varying NOT NULL,
    reference character varying(255) NOT NULL,
    seat_count integer DEFAULT 0 NOT NULL,
    unit_price integer DEFAULT 0 NOT NULL,
    total_amount integer DEFAULT 0 NOT NULL,
    status character varying(255) DEFAULT 'paid'::character varying NOT NULL,
    metadata_json json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.subscription_payments OWNER TO normchat;

--
-- Name: subscription_payments_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.subscription_payments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.subscription_payments_id_seq OWNER TO normchat;

--
-- Name: subscription_payments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.subscription_payments_id_seq OWNED BY public.subscription_payments.id;


--
-- Name: subscription_seats; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.subscription_seats (
    id bigint NOT NULL,
    subscription_id bigint NOT NULL,
    user_id bigint NOT NULL,
    seat_type character varying(255) DEFAULT 'included'::character varying NOT NULL,
    active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.subscription_seats OWNER TO normchat;

--
-- Name: subscription_seats_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.subscription_seats_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.subscription_seats_id_seq OWNER TO normchat;

--
-- Name: subscription_seats_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.subscription_seats_id_seq OWNED BY public.subscription_seats.id;


--
-- Name: subscriptions; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.subscriptions (
    id bigint NOT NULL,
    group_id bigint NOT NULL,
    plan_name character varying(255) DEFAULT 'normchat-main'::character varying NOT NULL,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    billing_cycle character varying(255) DEFAULT 'monthly'::character varying NOT NULL,
    main_price numeric(12,2) DEFAULT '99'::numeric NOT NULL,
    included_seats integer DEFAULT 2 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.subscriptions OWNER TO normchat;

--
-- Name: subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.subscriptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.subscriptions_id_seq OWNER TO normchat;

--
-- Name: subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.subscriptions_id_seq OWNED BY public.subscriptions.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: normchat
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    avatar_url character varying(255),
    auth_provider character varying(255) DEFAULT 'chatgpt'::character varying NOT NULL,
    provider_user_id character varying(255),
    email_verified_at timestamp(0) without time zone,
    password character varying(255),
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    access_token_encrypted text,
    refresh_token_encrypted text,
    token_expires_at timestamp(0) without time zone,
    api_key_encrypted text,
    interdotz_id character varying(255)
);


ALTER TABLE public.users OWNER TO normchat;

--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: normchat
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO normchat;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: normchat
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: ai_connections id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.ai_connections ALTER COLUMN id SET DEFAULT nextval('public.ai_connections_id_seq'::regclass);


--
-- Name: approvals id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.approvals ALTER COLUMN id SET DEFAULT nextval('public.approvals_id_seq'::regclass);


--
-- Name: audit_logs id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.audit_logs ALTER COLUMN id SET DEFAULT nextval('public.audit_logs_id_seq'::regclass);


--
-- Name: chat_message_queues id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.chat_message_queues ALTER COLUMN id SET DEFAULT nextval('public.chat_message_queues_id_seq'::regclass);


--
-- Name: exports id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.exports ALTER COLUMN id SET DEFAULT nextval('public.exports_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: group_backups id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.group_backups ALTER COLUMN id SET DEFAULT nextval('public.group_backups_id_seq'::regclass);


--
-- Name: group_members id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.group_members ALTER COLUMN id SET DEFAULT nextval('public.group_members_id_seq'::regclass);


--
-- Name: group_token_contributions id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.group_token_contributions ALTER COLUMN id SET DEFAULT nextval('public.group_token_contributions_id_seq'::regclass);


--
-- Name: group_tokens id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.group_tokens ALTER COLUMN id SET DEFAULT nextval('public.group_tokens_id_seq'::regclass);


--
-- Name: groups id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.groups ALTER COLUMN id SET DEFAULT nextval('public.groups_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: message_versions id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.message_versions ALTER COLUMN id SET DEFAULT nextval('public.message_versions_id_seq'::regclass);


--
-- Name: messages id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.messages ALTER COLUMN id SET DEFAULT nextval('public.messages_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: pending_payments id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.pending_payments ALTER COLUMN id SET DEFAULT nextval('public.pending_payments_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: poll_votes id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.poll_votes ALTER COLUMN id SET DEFAULT nextval('public.poll_votes_id_seq'::regclass);


--
-- Name: recovery_logs id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.recovery_logs ALTER COLUMN id SET DEFAULT nextval('public.recovery_logs_id_seq'::regclass);


--
-- Name: role_permissions id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.role_permissions ALTER COLUMN id SET DEFAULT nextval('public.role_permissions_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: subscription_payments id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.subscription_payments ALTER COLUMN id SET DEFAULT nextval('public.subscription_payments_id_seq'::regclass);


--
-- Name: subscription_seats id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.subscription_seats ALTER COLUMN id SET DEFAULT nextval('public.subscription_seats_id_seq'::regclass);


--
-- Name: subscriptions id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.subscriptions ALTER COLUMN id SET DEFAULT nextval('public.subscriptions_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Data for Name: ai_connections; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.ai_connections (id, user_id, provider, access_token, refresh_token, expires_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: approvals; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.approvals (id, group_id, user_id, status, requested_at, approved_by, rejected_by, note, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: audit_logs; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.audit_logs (id, group_id, actor_id, action, target_type, target_id, metadata_json, created_at) FROM stdin;
1	\N	1	auth.connect	App\\Models\\User	1	{"provider":"interdotz","method":"sso"}	2026-04-07 18:14:50
2	1	1	group.create	App\\Models\\Group	1	{"ai_provider":"openai","ai_model":"gpt-4.1"}	2026-04-07 18:16:35
3	1	1	chat.send_message	App\\Models\\Message	1	\N	2026-04-07 18:17:09
4	\N	2	auth.connect	App\\Models\\User	2	{"provider":"interdotz","method":"sso"}	2026-04-07 18:18:49
5	2	1	group.create	App\\Models\\Group	2	{"ai_provider":"openai","ai_model":"gpt-4.1"}	2026-04-07 18:25:33
6	3	1	group.create	App\\Models\\Group	3	{"ai_provider":"openai","ai_model":"gpt-5"}	2026-04-07 18:45:47
7	3	1	chat.send_message	App\\Models\\Message	2	\N	2026-04-07 18:46:02
8	3	1	chat.send_message	App\\Models\\Message	3	\N	2026-04-07 18:46:09
9	3	2	group.member_joined	App\\Models\\Group	3	{"patungan":10000,"seat_fee":4000,"normkredit":10,"simulated":true}	2026-04-07 19:12:55
10	3	2	chat.send_message	App\\Models\\Message	7	\N	2026-04-07 19:13:12
11	3	2	chat.send_message	App\\Models\\Message	8	\N	2026-04-07 19:13:28
12	3	2	chat.send_message	App\\Models\\Message	10	\N	2026-04-07 19:13:49
13	3	2	chat.send_message	App\\Models\\Message	11	\N	2026-04-07 19:31:36
14	3	2	chat.send_message	App\\Models\\Message	12	\N	2026-04-07 19:31:45
15	3	2	chat.send_message	App\\Models\\Message	13	\N	2026-04-07 19:32:35
16	3	2	chat.send_message	App\\Models\\Message	15	\N	2026-04-07 19:35:13
17	3	2	chat.send_message	App\\Models\\Message	17	\N	2026-04-07 19:36:32
18	3	2	chat.send_message	App\\Models\\Message	18	\N	2026-04-07 19:58:53
19	3	2	chat.send_message	App\\Models\\Message	19	\N	2026-04-07 19:59:45
20	\N	1	auth.connect	App\\Models\\User	1	{"provider":"interdotz","method":"sso"}	2026-04-08 08:44:51
21	3	2	chat.send_message	App\\Models\\Message	21	\N	2026-04-08 09:29:15
22	4	2	group.create	App\\Models\\Group	4	{"ai_provider":"openai","ai_model":"gpt-5"}	2026-04-08 10:14:46
23	4	2	chat.send_message	App\\Models\\Message	23	\N	2026-04-08 10:15:02
24	4	2	chat.send_message	App\\Models\\Message	24	\N	2026-04-08 10:15:43
25	4	2	chat.send_message	App\\Models\\Message	25	\N	2026-04-08 10:15:49
26	4	2	chat.send_message	App\\Models\\Message	27	\N	2026-04-08 10:16:18
27	2	2	group.member_joined	App\\Models\\Group	2	{"patungan":10000,"seat_fee":4000,"normkredit":10,"simulated":true}	2026-04-08 10:18:27
28	2	1	chat.send_message	App\\Models\\Message	29	\N	2026-04-08 10:18:38
29	2	2	chat.send_message	App\\Models\\Message	30	\N	2026-04-08 10:19:22
30	3	2	chat.send_message	App\\Models\\Message	31	\N	2026-04-08 10:31:10
31	3	2	chat.send_message	App\\Models\\Message	33	\N	2026-04-08 11:00:13
32	4	2	chat.send_message	App\\Models\\Message	35	\N	2026-04-10 10:47:51
33	\N	1	auth.logout	App\\Models\\User	1	\N	2026-04-11 09:35:21
34	\N	3	auth.connect	App\\Models\\User	3	{"provider":"interdotz","method":"sso"}	2026-04-11 09:35:35
35	\N	3	auth.logout	App\\Models\\User	3	\N	2026-04-11 16:53:34
36	\N	3	auth.connect	App\\Models\\User	3	{"provider":"interdotz","method":"sso"}	2026-04-11 16:53:47
37	\N	3	auth.logout	App\\Models\\User	3	\N	2026-04-11 16:54:00
38	\N	4	auth.connect	App\\Models\\User	4	{"provider":"interdotz","method":"sso"}	2026-04-11 16:54:13
39	\N	2	auth.logout	App\\Models\\User	2	\N	2026-04-12 16:39:46
40	\N	1	auth.connect	App\\Models\\User	1	{"provider":"interdotz","method":"sso"}	2026-04-12 16:40:16
41	\N	1	auth.logout	App\\Models\\User	1	\N	2026-04-12 19:34:31
42	\N	1	auth.connect	App\\Models\\User	1	{"provider":"interdotz","method":"sso"}	2026-04-12 20:07:48
43	\N	1	auth.logout	App\\Models\\User	1	\N	2026-04-12 20:12:47
44	\N	1	auth.connect	App\\Models\\User	1	{"provider":"interdotz","method":"sso"}	2026-04-12 20:13:04
45	\N	1	auth.logout	App\\Models\\User	1	\N	2026-04-12 20:15:36
46	\N	1	auth.connect	App\\Models\\User	1	{"provider":"interdotz","method":"sso"}	2026-04-12 20:15:51
47	\N	1	auth.logout	App\\Models\\User	1	\N	2026-04-12 20:17:12
48	\N	1	auth.connect	App\\Models\\User	1	{"provider":"interdotz","method":"sso"}	2026-04-12 20:18:47
49	\N	1	auth.logout	App\\Models\\User	1	\N	2026-04-12 20:21:18
50	\N	1	auth.connect	App\\Models\\User	1	{"provider":"interdotz","method":"sso"}	2026-04-12 20:21:37
51	5	1	group.create	App\\Models\\Group	5	{"ai_provider":"openai","ai_model":"gpt-5"}	2026-04-12 20:22:07
52	5	1	chat.send_message	App\\Models\\Message	37	\N	2026-04-12 20:22:42
53	5	1	chat.send_message	App\\Models\\Message	39	\N	2026-04-12 20:33:36
54	5	1	chat.send_message	App\\Models\\Message	41	\N	2026-04-12 20:34:08
55	5	1	chat.send_message	App\\Models\\Message	42	\N	2026-04-12 20:34:15
56	5	1	chat.send_message	App\\Models\\Message	44	\N	2026-04-12 20:34:42
57	5	1	chat.send_message	App\\Models\\Message	46	\N	2026-04-12 20:35:07
58	5	1	chat.send_message	App\\Models\\Message	48	\N	2026-04-12 20:39:14
59	5	1	chat.send_message	App\\Models\\Message	50	\N	2026-04-12 20:41:40
60	\N	1	auth.logout	App\\Models\\User	1	\N	2026-04-13 08:04:42
61	\N	1	auth.connect	App\\Models\\User	1	{"provider":"interdotz","method":"sso"}	2026-04-13 08:04:59
62	8	1	group.create	App\\Models\\Group	8	{"du_paid":0,"transaction_id":null,"normkredits":12}	2026-04-13 08:15:34
63	\N	4	auth.logout	App\\Models\\User	4	\N	2026-04-14 01:56:45
64	\N	5	auth.connect	App\\Models\\User	5	{"provider":"interdotz","method":"sso"}	2026-04-14 09:36:34
65	\N	5	auth.logout	App\\Models\\User	5	\N	2026-04-14 09:44:49
66	\N	3	auth.connect	App\\Models\\User	3	{"provider":"interdotz","method":"sso"}	2026-04-14 09:44:59
67	\N	3	auth.logout	App\\Models\\User	3	\N	2026-04-14 10:10:25
68	\N	1	auth.connect	App\\Models\\User	1	{"provider":"interdotz","method":"sso"}	2026-04-14 10:12:09
69	3	1	chat.send_message	App\\Models\\Message	52	\N	2026-04-14 10:31:37
70	3	1	chat.send_message	App\\Models\\Message	54	\N	2026-04-14 10:31:54
71	\N	1	auth.logout	App\\Models\\User	1	\N	2026-04-14 10:43:29
72	\N	1	auth.connect	App\\Models\\User	1	{"provider":"interdotz","method":"sso"}	2026-04-14 10:43:50
73	\N	1	auth.logout	App\\Models\\User	1	\N	2026-04-14 15:03:14
74	\N	1	auth.connect	App\\Models\\User	1	{"provider":"interdotz","method":"sso"}	2026-04-14 15:03:30
75	\N	1	auth.logout	App\\Models\\User	1	\N	2026-04-14 17:24:49
76	\N	6	auth.connect	App\\Models\\User	6	{"provider":"interdotz","method":"sso"}	2026-04-14 17:46:39
77	\N	6	auth.logout	App\\Models\\User	6	\N	2026-04-14 18:39:56
78	\N	6	auth.connect	App\\Models\\User	6	{"provider":"interdotz","method":"sso"}	2026-04-14 18:40:08
79	\N	6	auth.logout	App\\Models\\User	6	\N	2026-04-14 18:41:11
80	\N	7	auth.connect	App\\Models\\User	7	{"provider":"interdotz","method":"sso"}	2026-04-14 18:43:06
81	35	7	group.create	App\\Models\\Group	35	{"du_paid":175,"transaction_id":"01KP6VKXGX1RP9ZXJYGAS6QR4T","normkredits":12}	2026-04-14 20:41:08
82	35	7	chat.send_message	App\\Models\\Message	55	\N	2026-04-14 20:42:19
83	\N	7	auth.connect	App\\Models\\User	7	{"provider":"interdotz","method":"sso"}	2026-04-14 20:47:30
84	35	7	chat.edit_message	App\\Models\\Message	55	{"old_length":23,"new_length":24}	2026-04-14 20:48:05
85	35	7	chat.send_message	App\\Models\\Message	56	\N	2026-04-14 20:48:58
86	\N	7	auth.logout	App\\Models\\User	7	\N	2026-04-14 21:17:10
87	\N	7	auth.logout	App\\Models\\User	7	\N	2026-04-14 21:17:12
88	\N	7	auth.connect	App\\Models\\User	7	{"provider":"interdotz","method":"sso"}	2026-04-14 21:22:56
89	35	7	chat.send_message	App\\Models\\Message	57	\N	2026-04-14 21:23:24
90	35	7	chat.send_message	App\\Models\\Message	58	\N	2026-04-14 21:24:15
91	\N	8	auth.connect	App\\Models\\User	8	{"provider":"interdotz","method":"sso"}	2026-04-14 21:25:18
92	35	8	group.member_joined	App\\Models\\Group	35	{"du_paid":25,"transaction_id":"01KP6Y658F5JFQK7M3S11QJ2D5","patungan":true}	2026-04-14 21:26:03
93	35	7	chat.send_message	App\\Models\\Message	60	\N	2026-04-14 21:28:08
94	35	7	chat.send_message	App\\Models\\Message	62	\N	2026-04-14 21:28:47
95	35	8	chat.send_message	App\\Models\\Message	63	\N	2026-04-14 21:29:16
96	35	7	chat.send_message	App\\Models\\Message	64	\N	2026-04-14 21:29:23
97	35	7	chat.send_message	App\\Models\\Message	65	\N	2026-04-14 21:30:11
98	35	7	group.member_role_changed	App\\Models\\GroupMember	11	{"role":"admin"}	2026-04-14 21:33:34
99	35	7	chat.send_message	App\\Models\\Message	67	\N	2026-04-14 21:42:13
100	35	7	chat.send_message	App\\Models\\Message	68	\N	2026-04-14 21:48:12
101	\N	7	auth.connect	App\\Models\\User	7	{"provider":"interdotz","method":"sso"}	2026-04-15 08:19:24
102	35	7	chat.delete_message	App\\Models\\Message	68	{"sender_type":"user","sender_id":7,"message_type":"text"}	2026-04-15 08:25:06
103	\N	7	auth.logout	App\\Models\\User	7	\N	2026-04-15 15:20:15
104	\N	8	auth.connect	App\\Models\\User	8	{"provider":"interdotz","method":"sso"}	2026-04-15 15:20:34
\.


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.cache (key, value, expiration) FROM stdin;
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: chat_message_queues; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.chat_message_queues (id, group_id, message_id, status, queued_at, processed_at, error_message, created_at, updated_at) FROM stdin;
1	1	1	processed	2026-04-07 18:17:09	2026-04-07 18:17:10	\N	2026-04-07 18:17:09	2026-04-07 18:17:10
2	3	2	processed	2026-04-07 18:46:02	2026-04-07 18:46:02	\N	2026-04-07 18:46:02	2026-04-07 18:46:02
3	3	3	processed	2026-04-07 18:46:09	2026-04-07 18:46:10	\N	2026-04-07 18:46:09	2026-04-07 18:46:10
4	3	5	processed	2026-04-07 19:03:29	2026-04-07 19:03:32	\N	2026-04-07 19:03:29	2026-04-07 19:03:32
5	3	7	processed	2026-04-07 19:13:12	2026-04-07 19:13:13	\N	2026-04-07 19:13:12	2026-04-07 19:13:13
6	3	8	processed	2026-04-07 19:13:28	2026-04-07 19:13:31	\N	2026-04-07 19:13:28	2026-04-07 19:13:31
7	3	10	processed	2026-04-07 19:13:49	2026-04-07 19:13:50	\N	2026-04-07 19:13:49	2026-04-07 19:13:50
8	3	11	processed	2026-04-07 19:31:36	2026-04-07 19:31:36	\N	2026-04-07 19:31:36	2026-04-07 19:31:36
9	3	12	processed	2026-04-07 19:31:45	2026-04-07 19:31:45	\N	2026-04-07 19:31:45	2026-04-07 19:31:45
10	3	13	processed	2026-04-07 19:32:35	2026-04-07 19:32:38	\N	2026-04-07 19:32:35	2026-04-07 19:32:38
11	3	15	processed	2026-04-07 19:35:13	2026-04-07 19:35:17	\N	2026-04-07 19:35:13	2026-04-07 19:35:17
12	3	19	processed	2026-04-07 19:59:45	2026-04-07 19:59:47	\N	2026-04-07 19:59:45	2026-04-07 19:59:47
13	3	21	processed	2026-04-08 09:29:15	2026-04-08 09:29:15	\N	2026-04-08 09:29:15	2026-04-08 09:29:15
14	4	25	processed	2026-04-08 10:15:49	2026-04-08 10:15:55	\N	2026-04-08 10:15:49	2026-04-08 10:15:55
15	4	27	processed	2026-04-08 10:16:18	2026-04-08 10:16:21	\N	2026-04-08 10:16:18	2026-04-08 10:16:21
16	3	31	processed	2026-04-08 10:31:10	2026-04-08 10:31:13	\N	2026-04-08 10:31:10	2026-04-08 10:31:13
17	3	33	processed	2026-04-08 11:00:13	2026-04-08 11:00:16	\N	2026-04-08 11:00:13	2026-04-08 11:00:16
18	4	35	processed	2026-04-10 10:47:51	2026-04-10 10:47:58	\N	2026-04-10 10:47:51	2026-04-10 10:47:58
19	5	37	processed	2026-04-12 20:22:42	2026-04-12 20:22:48	\N	2026-04-12 20:22:42	2026-04-12 20:22:48
20	5	39	processed	2026-04-12 20:33:36	2026-04-12 20:33:40	\N	2026-04-12 20:33:36	2026-04-12 20:33:40
21	5	42	processed	2026-04-12 20:34:15	2026-04-12 20:34:20	\N	2026-04-12 20:34:15	2026-04-12 20:34:20
22	5	44	processed	2026-04-12 20:34:42	2026-04-12 20:34:47	\N	2026-04-12 20:34:42	2026-04-12 20:34:47
23	5	46	processed	2026-04-12 20:35:07	2026-04-12 20:35:09	\N	2026-04-12 20:35:07	2026-04-12 20:35:09
24	5	48	processed	2026-04-12 20:39:14	2026-04-12 20:39:17	\N	2026-04-12 20:39:14	2026-04-12 20:39:17
25	5	50	processed	2026-04-12 20:41:40	2026-04-12 20:42:00	\N	2026-04-12 20:41:40	2026-04-12 20:42:00
26	3	52	processed	2026-04-14 10:31:37	2026-04-14 10:31:41	\N	2026-04-14 10:31:37	2026-04-14 10:31:41
27	35	58	processed	2026-04-14 21:24:15	2026-04-14 21:24:19	\N	2026-04-14 21:24:15	2026-04-14 21:24:19
28	35	60	processed	2026-04-14 21:28:08	2026-04-14 21:28:11	\N	2026-04-14 21:28:08	2026-04-14 21:28:11
29	35	65	processed	2026-04-14 21:30:11	2026-04-14 21:30:13	\N	2026-04-14 21:30:11	2026-04-14 21:30:13
\.


--
-- Data for Name: exports; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.exports (id, group_id, file_name, storage_path, file_type, status, created_by, created_at) FROM stdin;
1	35	group-35-export-1.pdf	group-35-export-1.pdf	pdf	done	7	2026-04-14 21:30:49
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- Data for Name: group_backups; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.group_backups (id, group_id, backup_type, storage_path, created_by, created_at) FROM stdin;
1	35	snapshot	group-35-backup-20260414213402.json	7	2026-04-14 21:34:02
\.


--
-- Data for Name: group_members; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.group_members (id, group_id, user_id, role_id, status, invited_by, approved_by, joined_at, created_at, updated_at, last_read_message_id, last_read_at) FROM stdin;
2	1	1	1	active	\N	\N	2026-04-07 19:03:01	2026-04-07 19:03:01	2026-04-07 19:03:01	\N	\N
3	2	1	1	active	\N	\N	2026-04-07 19:03:01	2026-04-07 19:03:01	2026-04-07 19:03:01	\N	\N
4	3	1	1	active	\N	\N	2026-04-07 19:03:01	2026-04-07 19:03:01	2026-04-07 19:03:01	\N	\N
5	3	2	3	active	\N	\N	2026-04-07 19:12:55	2026-04-07 19:12:55	2026-04-07 19:12:55	\N	\N
6	4	2	1	active	\N	\N	2026-04-08 10:14:46	2026-04-08 10:14:46	2026-04-08 10:14:46	\N	\N
7	2	2	3	active	\N	\N	2026-04-08 10:18:27	2026-04-08 10:18:27	2026-04-08 10:18:27	\N	\N
8	5	1	1	active	\N	\N	2026-04-12 20:22:07	2026-04-12 20:22:07	2026-04-12 20:22:07	\N	\N
9	8	1	1	active	\N	\N	2026-04-13 08:15:34	2026-04-13 08:15:34	2026-04-13 08:15:34	\N	\N
10	35	7	1	active	\N	\N	2026-04-14 20:41:08	2026-04-14 20:41:08	2026-04-14 21:48:12	68	2026-04-14 21:48:12
11	35	8	2	active	\N	\N	2026-04-14 21:26:02	2026-04-14 21:26:02	2026-04-14 21:48:13	68	2026-04-14 21:48:13
\.


--
-- Data for Name: group_token_contributions; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.group_token_contributions (id, group_id, user_id, source, token_amount, price_paid, payment_reference, created_at, updated_at) FROM stdin;
1	1	1	subscription	10000	25000	\N	2026-04-07 18:16:35	2026-04-07 18:16:35
2	2	1	subscription	10000	25000	\N	2026-04-07 18:25:33	2026-04-07 18:25:33
3	3	1	subscription	10000	25000	\N	2026-04-07 18:45:47	2026-04-07 18:45:47
4	3	2	patungan_simulated	10000	0	SIM-JOIN-VEXPQHYR	2026-04-07 18:48:52	2026-04-07 18:48:52
5	3	2	patungan	10000	0	SIM-JOIN-8OCPZGJN	2026-04-07 19:12:55	2026-04-07 19:12:55
6	4	2	subscription	10000	25000	\N	2026-04-08 10:14:46	2026-04-08 10:14:46
7	2	2	patungan	10000	0	SIM-JOIN-GXBHKGHC	2026-04-08 10:18:27	2026-04-08 10:18:27
8	5	1	subscription	30000	30000	\N	2026-04-12 20:22:07	2026-04-12 20:22:07
9	8	1	group_creation	30000	0	\N	2026-04-13 08:15:34	2026-04-13 08:15:34
10	35	7	group_creation	30000	175	01KP6VKXGX1RP9ZXJYGAS6QR4T	2026-04-14 20:41:08	2026-04-14 20:41:08
11	35	8	patungan	0	25	01KP6Y658F5JFQK7M3S11QJ2D5	2026-04-14 21:26:03	2026-04-14 21:26:03
\.


--
-- Data for Name: group_tokens; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.group_tokens (id, group_id, total_tokens, used_tokens, remaining_tokens, created_at, updated_at) FROM stdin;
1	1	10000	0	10000	2026-04-07 18:16:35	2026-04-07 18:16:35
2	2	20000	0	20000	2026-04-07 18:25:33	2026-04-08 10:18:27
4	4	10000	1178	8822	2026-04-08 10:14:46	2026-04-10 10:47:58
5	5	30000	15120	14880	2026-04-12 20:22:07	2026-04-12 20:42:00
6	8	30000	0	30000	2026-04-13 08:15:34	2026-04-13 08:15:34
3	3	30000	4353	25647	2026-04-07 18:45:47	2026-04-14 10:31:41
7	35	30000	991	29009	2026-04-14 20:41:08	2026-04-14 21:30:13
\.


--
-- Data for Name: groups; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.groups (id, name, description, owner_id, password_hash, approval_enabled, created_at, updated_at, deleted_at, share_id, ai_provider, ai_model, ai_persona_style, ai_persona_guardrails, status) FROM stdin;
1	Group testing	ini testing aja	1	$2y$12$k/XQSKtyjy.nLSgBG1E1iOvRaiXM01b6Jh3tScxJ0P//1M7eXlr/m	t	2026-04-07 18:16:35	2026-04-07 18:16:35	\N	VLR4ZP	openai	gpt-4.1	\N	\N	active
2	Testing Group 2	ini testing	1	$2y$12$yppRChweek0Gyq6dvPSgXehfORysXIH/ifaDac1eGlA0Dn8X11d5W	t	2026-04-07 18:25:33	2026-04-07 18:25:33	\N	FVXXLI	openai	gpt-4.1	\N	\N	active
3	Testing Group 3	ini testing	1	$2y$12$XMSdy3zRi7NLtkzSxKdd1utB/4c1gQFLC8nnQO7HwNF6yyz1DOpA.	t	2026-04-07 18:45:47	2026-04-07 18:45:47	\N	TXVX9Y	openai	gpt-5	\N	\N	active
4	Testing user 1	ini testing	2	$2y$12$Q1KtzxpNAFpHs7GuRgD/HedGKdEP3XdovGwaeyjQrLVBpu7K6.Etq	t	2026-04-08 10:14:45	2026-04-08 10:14:45	\N	HOEBUY	openai	gpt-5	\N	\N	active
5	testing grup pay	ini	1	$2y$12$d46Y3fBEK4nL8dVqI.Bm9u7cn/gq.k5HRqWWq2Tuh4b..ugyVgWsK	t	2026-04-12 20:22:07	2026-04-12 20:22:07	\N	Y7UJQD	openai	gpt-5	\N	\N	active
6	tesss	tess	1	$2y$12$WVWOp3r1nfMStfOouoQ0nu4lSC/Wc5tIa8oD5qWeqLloZoSu2E/SO	t	2026-04-13 08:05:25	2026-04-13 08:05:25	2026-04-13 08:05:25	AKU3JH	openai	gpt-5	\N	\N	pending_payment
7	tess	tess	1	$2y$12$AaMcF5ooU3fMh/DjPaUNO.emWAHioeEHWqHa5lkky5LE/Wy6bvcHu	t	2026-04-13 08:08:51	2026-04-13 08:08:51	2026-04-13 08:08:51	MA118G	openai	gpt-5	\N	\N	pending_payment
8	test	test	1	$2y$12$5D8pci5VBZGEBsOi6FxpeO89icyAEEvEuj07v0mlvfvQpwW8mD.GG	t	2026-04-13 08:15:34	2026-04-13 08:15:34	\N	RUF1GR	openai	gpt-5	\N	\N	active
21	Grup Baru	ini grup baru	1	$2y$12$TcgJiP6xS9Ks9A7KLz5LiutojjrhtCaYiL1r6RhRvwB/7z3KTtbaC	t	2026-04-14 10:12:38	2026-04-14 10:12:38	\N	DMXATG	openai	gpt-5	\N	\N	pending_payment
22	testing	ini testing	1	$2y$12$Ceu3lKMVtXgKPZGfZLk22.MAYRBVtE6Owz46KvB8AsdCCjnhnw/Oi	t	2026-04-14 10:15:29	2026-04-14 10:15:29	\N	5WDMKL	openai	gpt-5	\N	\N	pending_payment
23	testing	ini testing	1	$2y$12$W/f4kKoGvi.yHcbbBYU7tet2Oc6HJUP0phA9hpFSt7QW3IE4.TRgS	t	2026-04-14 10:42:28	2026-04-14 10:42:28	\N	F0XJ8E	openai	gpt-5	\N	\N	pending_payment
24	testing444	ini testing aja	1	$2y$12$KbF0TtNJ5ZMb/K44DxZF7e6e31ESlbhckQNGLa02d9PtNrqEMgf1C	f	2026-04-14 10:44:09	2026-04-14 10:44:09	\N	N7T8Q1	openai	gpt-5	\N	\N	pending_payment
25	testing	testing aja	1	$2y$12$fbTkx0YdMb/.K8RNhot5guQzpl54X4e2AvvlEmhCb3exqr508wgL2	t	2026-04-14 15:02:58	2026-04-14 15:02:58	\N	2XWVPB	openai	gpt-5	\N	\N	pending_payment
26	testing	ini testing	1	$2y$12$0vOmPT3Ugu2w4s6DpD9Qzee0.1NWyWU7ZSGlVnXswu/mWO4ZBu.ca	t	2026-04-14 15:03:45	2026-04-14 15:03:45	\N	C5GBS0	openai	gpt-5	\N	\N	pending_payment
29	testing	ini testing	6	$2y$12$chGZHxzwPfKZCo4x4nEiC.N1aEjWqI8/cdXUqMrsSAu8zGAzMqB1W	t	2026-04-14 17:56:36	2026-04-14 17:56:36	\N	U78EVK	openai	gpt-5	\N	\N	pending_payment
30	testing	testing aja	6	$2y$12$X0MRKYDyHDQjHwNUXiq50upTRvJ8OCfXr.kPJp6sqRAGVLMPo8mnS	t	2026-04-14 18:04:27	2026-04-14 18:04:27	\N	QYCGET	openai	gpt-5	\N	\N	pending_payment
35	Grup Baru	ini grup ai	7	$2y$12$yAaHM/SEStYIRcHKNpABPue7CaQcHavLDkkHL2S8IWNsrcuFo4C.q	t	2026-04-14 20:41:07	2026-04-14 20:41:08	\N	E5YTXA	openai	gpt-5	\N	\N	active
\.


--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- Data for Name: message_versions; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.message_versions (id, message_id, version_number, content_snapshot, edited_by, edited_at, created_at, updated_at) FROM stdin;
1	55	1	ini adalah grup pertama	7	2026-04-14 20:48:05	2026-04-14 20:48:05	2026-04-14 20:48:05
\.


--
-- Data for Name: messages; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.messages (id, group_id, sender_type, sender_id, content, created_at, updated_at, deleted_at, message_type, attachment_disk, attachment_path, attachment_mime, attachment_original_name, attachment_size, reply_to_message_id) FROM stdin;
1	1	user	1	halo	2026-04-07 18:17:09	2026-04-07 18:17:09	\N	text	\N	\N	\N	\N	\N	\N
2	3	user	1	hali	2026-04-07 18:46:02	2026-04-07 18:46:02	\N	text	\N	\N	\N	\N	\N	\N
3	3	user	1	@ai halo	2026-04-07 18:46:09	2026-04-07 18:46:09	\N	text	\N	\N	\N	\N	\N	\N
4	3	ai	\N	AI sedang sibuk dan belum bisa merespons saat ini. Coba lagi beberapa saat.	2026-04-07 18:46:10	2026-04-07 18:46:10	\N	text	\N	\N	\N	\N	\N	\N
5	3	user	1	@ai tes respons	2026-04-07 19:03:29	2026-04-07 19:03:29	\N	text	\N	\N	\N	\N	\N	\N
6	3	ai	\N	Halo! Saya di sini dan siap membantu. Ada yang bisa saya bantu?	2026-04-07 19:03:32	2026-04-07 19:03:32	\N	text	\N	\N	\N	\N	\N	\N
7	3	user	2	halo	2026-04-07 19:13:12	2026-04-07 19:13:12	\N	text	\N	\N	\N	\N	\N	\N
8	3	user	2	@ai halo	2026-04-07 19:13:28	2026-04-07 19:13:28	\N	text	\N	\N	\N	\N	\N	\N
9	3	ai	\N	Halo juga! Ada yang bisa saya bantu hari ini?	2026-04-07 19:13:31	2026-04-07 19:13:31	\N	text	\N	\N	\N	\N	\N	\N
10	3	user	2	@testing2 woi ajg	2026-04-07 19:13:49	2026-04-07 19:13:49	\N	text	\N	\N	\N	\N	\N	\N
11	3	user	2	halo	2026-04-07 19:31:36	2026-04-07 19:31:36	\N	text	\N	\N	\N	\N	\N	\N
12	3	user	2	woi @testing2	2026-04-07 19:31:45	2026-04-07 19:31:45	\N	text	\N	\N	\N	\N	\N	\N
13	3	user	2	@ai kau siapa	2026-04-07 19:32:35	2026-04-07 19:32:35	\N	text	\N	\N	\N	\N	\N	\N
14	3	ai	\N	Saya adalah AI yang siap membantu menjawab pertanyaan atau berdiskusi di grup ini. Ada yang ingin kamu tanyakan?	2026-04-07 19:32:38	2026-04-07 19:32:38	\N	text	\N	\N	\N	\N	\N	\N
15	3	user	2	jelaskan padaku siapa sebenarnya Hitler @ai	2026-04-07 19:35:13	2026-04-07 19:35:13	\N	text	\N	\N	\N	\N	\N	\N
16	3	ai	\N	Adolf Hitler adalah pemimpin Jerman dari tahun 1934 sampai 1945 dan kepala Partai Nazi. Ia dikenal sebagai tokoh utama di balik Perang Dunia II dan Holocaust, yaitu pembunuhan massal jutaan orang, terutama Yahudi. Hitler lahir di Austria tahun 1889 dan meninggal tahun 1945. Ideologinya sangat rasis dan otoriter, serta membawa dampak besar dan tragis bagi sejarah dunia.	2026-04-07 19:35:17	2026-04-07 19:35:17	\N	text	\N	\N	\N	\N	\N	\N
17	3	user	2	@ai ini foto apa	2026-04-07 19:36:32	2026-04-07 19:36:32	\N	image	normchat_attachments	group-3/2026/04/a46307ca-68d7-43fa-bbeb-55b4488acdba.jpg	image/jpeg	17755905669366365964022016403958.jpg	5598211	\N
18	3	user	2	ini foto apa @ai	2026-04-07 19:58:53	2026-04-07 19:58:53	\N	image	normchat_attachments	group-3/2026/04/96d90b7f-2413-4bd5-96a3-b2cf8b0a7580.jpg	image/jpeg	IMG_20260407_232623_816.jpg	921912	\N
19	3	user	2	@ai halo	2026-04-07 19:59:45	2026-04-07 19:59:45	\N	text	\N	\N	\N	\N	\N	\N
20	3	ai	\N	Halo! Ada yang bisa saya bantu?	2026-04-07 19:59:47	2026-04-07 19:59:47	\N	text	\N	\N	\N	\N	\N	\N
21	3	user	2	halo	2026-04-08 09:29:15	2026-04-08 09:29:15	\N	text	\N	\N	\N	\N	\N	6
22	3	ai	\N	AI sedang sibuk dan belum bisa merespons saat ini. Coba lagi beberapa saat.	2026-04-08 09:29:15	2026-04-08 09:29:15	\N	text	\N	\N	\N	\N	\N	\N
23	4	user	2	\N	2026-04-08 10:15:02	2026-04-08 10:15:02	\N	voice	normchat_attachments	group-4/2026/04/d9087e5b-fc76-4e7d-968b-b64b9384406c.webm	audio/webm	voice-1775643301234.webm	39916	\N
24	4	user	2	\N	2026-04-08 10:15:43	2026-04-08 10:15:43	\N	image	normchat_attachments	group-4/2026/04/ebdd2f81-a1d7-4df9-8e9e-8ddf9419ea69.jpg	image/jpeg	17756433171031993991669003586959.jpg	4782242	\N
25	4	user	2	@ai apa kabar	2026-04-08 10:15:49	2026-04-08 10:15:49	\N	text	\N	\N	\N	\N	\N	\N
26	4	ai	\N	Hai! Aku baik, terima kasih sudah tanya. Ada yang bisa aku bantu hari ini?	2026-04-08 10:15:55	2026-04-08 10:15:55	\N	text	\N	\N	\N	\N	\N	\N
27	4	user	2	bagaimana pendapatmu mu tentang nazi?	2026-04-08 10:16:18	2026-04-08 10:16:18	\N	text	\N	\N	\N	\N	\N	26
28	4	ai	\N	Nazi adalah rezim yang sangat kontroversial dan dikenal karena kejahatan kemanusiaan, terutama Holocaust. Ideologi dan tindakan mereka sangat bertentangan dengan nilai-nilai kemanusiaan dan hak asasi manusia. Jadi, secara umum, pandangan terhadap Nazi sangat negatif.	2026-04-08 10:16:21	2026-04-08 10:16:21	\N	text	\N	\N	\N	\N	\N	\N
29	2	user	1	halo	2026-04-08 10:18:38	2026-04-08 10:18:38	\N	text	\N	\N	\N	\N	\N	\N
30	2	user	2	🙏	2026-04-08 10:19:22	2026-04-08 10:19:22	\N	text	\N	\N	\N	\N	\N	29
31	3	user	2	halo	2026-04-08 10:31:10	2026-04-08 10:31:10	\N	text	\N	\N	\N	\N	\N	16
32	3	ai	\N	Halo! Ada yang ingin kamu tanyakan lebih lanjut tentang Adolf Hitler atau topik lain? Saya siap membantu!	2026-04-08 10:31:13	2026-04-08 10:31:13	\N	text	\N	\N	\N	\N	\N	\N
33	3	user	2	@ai apa kabar	2026-04-08 11:00:13	2026-04-08 11:00:13	\N	text	\N	\N	\N	\N	\N	\N
34	3	ai	\N	Hai! Saya baik, terima kasih sudah bertanya. Kamu sendiri bagaimana? Ada yang ingin didiskusikan?	2026-04-08 11:00:16	2026-04-08 11:00:16	\N	text	\N	\N	\N	\N	\N	\N
35	4	user	2	iyakah?	2026-04-10 10:47:51	2026-04-10 10:47:51	\N	text	\N	\N	\N	\N	\N	28
36	4	ai	\N	Iya, benar. Nazi memang dikenal luas karena tindakan kejam dan pelanggaran hak asasi manusia yang mereka lakukan selama masa pemerintahannya, terutama pada Perang Dunia II. Banyak literatur sejarah dan bukti yang mendukung hal tersebut.	2026-04-10 10:47:58	2026-04-10 10:47:58	\N	text	\N	\N	\N	\N	\N	\N
37	5	user	1	@ai buatkan gambar jokowi	2026-04-12 20:22:42	2026-04-12 20:22:42	\N	text	\N	\N	\N	\N	\N	\N
38	5	ai	\N	Maaf, saya tidak bisa membuat gambar. Namun, saya bisa membantu mendeskripsikan Jokowi jika kamu mau!	2026-04-12 20:22:48	2026-04-12 20:22:48	\N	text	\N	\N	\N	\N	\N	\N
39	5	user	1	@ai buatin gambar jokowi	2026-04-12 20:33:36	2026-04-12 20:33:36	\N	text	\N	\N	\N	\N	\N	\N
40	5	ai	\N	Gagal membuat gambar. Your request was rejected as a result of our safety system. Your prompt may contain text that is not allowed by our safety system.	2026-04-12 20:33:40	2026-04-12 20:33:40	\N	text	\N	\N	\N	\N	\N	\N
41	5	user	1	buatkan table keuangan	2026-04-12 20:34:08	2026-04-12 20:34:08	\N	text	\N	\N	\N	\N	\N	\N
42	5	user	1	@ai	2026-04-12 20:34:15	2026-04-12 20:34:15	\N	text	\N	\N	\N	\N	\N	41
43	5	ai	\N	Berikut contoh tabel keuangan sederhana:\n\n| Tanggal     | Keterangan      | Pemasukan (Rp) | Pengeluaran (Rp) | Saldo (Rp) |\n|-------------|----------------|----------------|------------------|------------|\n| 01-06-2024  | Gaji Bulanan   | 5.000.000      | 0                | 5.000.000  |\n| 03-06-2024  | Belanja        | 0              | 500.000          | 4.500.000  |\n| 05-06-2024  | Transportasi   | 0              | 200.000          | 4.300.000  |\n| 10-06-2024  | Bonus          | 1.000.000      | 0                | 5.300.000  |\n| 15-06-2024  | Makan di luar  | 0              | 150.000          | 5.150.000  |\n\nKamu bisa menyesuaikan sesuai kebutuhan!	2026-04-12 20:34:20	2026-04-12 20:34:20	\N	text	\N	\N	\N	\N	\N	\N
44	5	user	1	buatkan diagram pertumbuhan ekonomi Indonesia @ai	2026-04-12 20:34:42	2026-04-12 20:34:42	\N	text	\N	\N	\N	\N	\N	\N
45	5	ai	\N	Berikut contoh diagram pertumbuhan ekonomi Indonesia dalam bentuk diagram garis sederhana menggunakan Mermaid:\n\n```mermaid\n%% Diagram pertumbuhan ekonomi Indonesia (dalam %)\ngraph LR\n    A[2019<br>5,0%] --> B[2020<br>-2,1%]\n    B --> C[2021<br>3,7%]\n    C --> D[2022<br>5,3%]\n    D --> E[2023<br>5,0%]\n```\n\nKeterangan:\n- Data merupakan persentase pertumbuhan ekonomi (PDB) Indonesia per tahun.\n- 2020 mengalami kontraksi akibat pandemi, setelah itu tumbuh kembali.\n\nJika ingin bentuk lain (misal pie chart atau bar chart), silakan info ya!	2026-04-12 20:34:47	2026-04-12 20:34:47	\N	text	\N	\N	\N	\N	\N	\N
46	5	user	1	buatin bentuk bar chart	2026-04-12 20:35:07	2026-04-12 20:35:07	\N	text	\N	\N	\N	\N	\N	45
47	5	ai	\N	Berikut diagram pertumbuhan ekonomi Indonesia dalam bentuk bar chart menggunakan Mermaid:\n\n```mermaid\n%% Bar chart pertumbuhan ekonomi Indonesia (dalam %)\nbar\n    title Pertumbuhan Ekonomi Indonesia (2019-2023)\n    x-axis 2019, 2020, 2021, 2022, 2023\n    y-axis Pertumbuhan (%)\n    data 5.0, -2.1, 3.7, 5.3, 5.0\n```\n\nKeterangan:\n- Data diambil dari persentase pertumbuhan PDB Indonesia per tahun.\n- Tahun 2020 negatif karena pandemi, tahun-tahun berikutnya kembali positif.	2026-04-12 20:35:09	2026-04-12 20:35:09	\N	text	\N	\N	\N	\N	\N	\N
48	5	user	1	buatin gambar jokowi @ai	2026-04-12 20:39:14	2026-04-12 20:39:14	\N	text	\N	\N	\N	\N	\N	\N
49	5	ai	\N	Gagal membuat gambar. Your request was rejected as a result of our safety system. Your prompt may contain text that is not allowed by our safety system.	2026-04-12 20:39:17	2026-04-12 20:39:17	\N	text	\N	\N	\N	\N	\N	\N
50	5	user	1	buatkan gambar makanan toba @ai	2026-04-12 20:41:40	2026-04-12 20:41:40	\N	text	\N	\N	\N	\N	\N	\N
51	5	ai	\N	🎨 An image display of 'Makanan Toba', a traditional Indonesian meal neatly plated. On the plate, there's a variety of food representing the cuisine of the Toba Batak people, including marinated grilled fish, a bowl of tangy, spicy tomato salsa, a serving of warm, fluffy rice, and a scattering of fresh, green vegetables.	2026-04-12 20:42:00	2026-04-12 20:42:00	\N	image	normchat_attachments	group-5/2026/04/ai-357891be-89b5-4540-8fd0-3d91c51693e1.png	image/png	normai-generated.png	\N	\N
52	3	user	1	kamu ingin apa?	2026-04-14 10:31:37	2026-04-14 10:31:37	\N	text	\N	\N	\N	\N	\N	34
53	3	ai	\N	Sebagai AI, saya tidak punya keinginan pribadi. Tugas saya hanya membantu dan menjawab pertanyaan kamu sebaik mungkin. Ada topik tertentu yang ingin kamu bahas?	2026-04-14 10:31:41	2026-04-14 10:31:41	\N	text	\N	\N	\N	\N	\N	\N
54	3	user	1	buatkan gambar dulu, gambar apa aja	2026-04-14 10:31:54	2026-04-14 10:31:54	\N	text	\N	\N	\N	\N	\N	\N
55	35	user	7	ini adalah grup pertamaa	2026-04-14 20:42:19	2026-04-14 20:48:05	\N	text	\N	\N	\N	\N	\N	\N
56	35	user	7	ini adalah grup pertamaa	2026-04-14 20:48:58	2026-04-14 20:48:58	\N	text	\N	\N	\N	\N	\N	55
57	35	user	7	pertama Tama	2026-04-14 21:23:24	2026-04-14 21:23:24	\N	text	\N	\N	\N	\N	\N	56
58	35	user	7	@ai halo	2026-04-14 21:24:15	2026-04-14 21:24:15	\N	text	\N	\N	\N	\N	\N	\N
59	35	ai	\N	Halo! 👋  \nSelamat datang di grup pertama kalian! Ada yang mau dibahas atau ditanyakan?	2026-04-14 21:24:19	2026-04-14 21:24:19	\N	text	\N	\N	\N	\N	\N	\N
60	35	user	7	@ai halo	2026-04-14 21:28:08	2026-04-14 21:28:08	\N	text	\N	\N	\N	\N	\N	\N
61	35	ai	\N	Halo juga! Ada yang bisa aku bantu hari ini? 😊	2026-04-14 21:28:11	2026-04-14 21:28:11	\N	text	\N	\N	\N	\N	\N	\N
62	35	user	7	woi @rapael	2026-04-14 21:28:47	2026-04-14 21:28:47	\N	text	\N	\N	\N	\N	\N	\N
63	35	user	8	oi	2026-04-14 21:29:16	2026-04-14 21:29:16	\N	text	\N	\N	\N	\N	\N	\N
64	35	user	7	bacot kau	2026-04-14 21:29:23	2026-04-14 21:29:23	\N	text	\N	\N	\N	\N	\N	63
65	35	user	7	kamu siapa	2026-04-14 21:30:11	2026-04-14 21:30:11	\N	text	\N	\N	\N	\N	\N	59
66	35	ai	\N	Aku adalah NormAI, asisten AI di grup ini. Aku di sini untuk membantu menjawab pertanyaan atau diskusi apa pun yang kalian butuhkan. Silakan tanya apa saja!	2026-04-14 21:30:13	2026-04-14 21:30:13	\N	text	\N	\N	\N	\N	\N	\N
67	35	user	7	\N	2026-04-14 21:42:13	2026-04-14 21:42:13	\N	voice	normchat_attachments	group-35/2026/04/ef44e879-6e9e-4ba6-9913-319819702680.webm	audio/webm	voice-1776202931919.webm	46678	\N
68	35	user	7	😔😔	2026-04-14 21:48:12	2026-04-15 08:25:06	2026-04-15 08:25:06	text	\N	\N	\N	\N	\N	\N
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2026_03_23_060600_create_normchat_domain_tables	1
5	2026_03_24_100000_add_oauth_tokens_to_users_table	1
6	2026_03_24_120000_create_chat_message_queues_table	1
7	2026_03_24_130000_create_subscription_payments_table	1
8	2026_03_24_140000_recreate_ai_connections_for_user_tokens	1
9	2026_03_25_100000_add_share_id_to_groups_table	1
10	2026_03_25_110000_alter_api_key_encrypted_column_type	1
11	2026_03_25_130000_add_message_attachment_columns	1
12	2026_03_25_150000_create_group_tokens_table	1
13	2026_03_25_160000_add_ai_provider_model_to_groups_and_credits	1
14	2026_03_25_200000_create_pending_payments_table	1
15	2026_03_25_210000_add_ai_persona_to_groups_table	1
16	2026_04_07_193500_add_reply_to_message_id_to_messages_table	2
17	2026_04_13_035201_add_status_to_groups_table	3
18	2026_04_14_120000_add_interdotz_id_to_users_table	4
19	2026_04_14_230000_add_read_state_to_group_members_table	5
20	2026_04_15_120000_create_poll_votes_table	6
\.


--
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.password_reset_tokens (email, token, created_at) FROM stdin;
\.


--
-- Data for Name: pending_payments; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.pending_payments (id, user_id, group_id, order_id, payment_type, expected_amount, status, metadata_json, paid_at, expires_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: permissions; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.permissions (id, key, name, description, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: poll_votes; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.poll_votes (id, group_id, poll_message_id, user_id, option_number, voted_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: recovery_logs; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.recovery_logs (id, group_id, backup_id, restored_by, restored_at, reason) FROM stdin;
\.


--
-- Data for Name: role_permissions; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.role_permissions (id, role_id, permission_id, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.roles (id, key, name, description, created_at, updated_at) FROM stdin;
1	owner	Owner	Group owner	2026-04-07 19:03:01	2026-04-07 19:03:01
2	admin	Admin	Group administrator	2026-04-07 19:03:01	2026-04-07 19:03:01
3	member	Member	Group member	2026-04-07 19:03:01	2026-04-07 19:03:01
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
\.


--
-- Data for Name: subscription_payments; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.subscription_payments (id, subscription_id, group_id, created_by, payment_type, reference, seat_count, unit_price, total_amount, status, metadata_json, created_at, updated_at) FROM stdin;
1	3	3	1	add_seat	SIM-SEAT-ZOO0GFUS	1	4000	0	paid	{"simulated":true,"simulated_amount":4000}	2026-04-08 14:39:29	2026-04-08 14:39:29
\.


--
-- Data for Name: subscription_seats; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.subscription_seats (id, subscription_id, user_id, seat_type, active, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: subscriptions; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.subscriptions (id, group_id, plan_name, status, billing_cycle, main_price, included_seats, created_at, updated_at) FROM stdin;
1	1	normchat-pro	active	monthly	25000.00	2	2026-04-07 18:16:35	2026-04-07 18:16:35
4	4	normchat-pro	active	monthly	25000.00	2	2026-04-08 10:14:46	2026-04-08 10:14:46
2	2	normchat-pro	active	monthly	25000.00	3	2026-04-07 18:25:33	2026-04-08 10:18:27
3	3	normchat-pro	active	monthly	25000.00	5	2026-04-07 18:45:47	2026-04-08 14:39:29
5	5	normchat-pro	active	monthly	30000.00	2	2026-04-12 20:22:07	2026-04-12 20:22:07
6	8	normchat-pro	active	monthly	0.00	2	2026-04-13 08:15:34	2026-04-13 08:15:34
7	35	normchat-pro	active	monthly	175.00	3	2026-04-14 20:41:08	2026-04-14 21:26:03
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: normchat
--

COPY public.users (id, name, email, avatar_url, auth_provider, provider_user_id, email_verified_at, password, remember_token, created_at, updated_at, access_token_encrypted, refresh_token_encrypted, token_expires_at, api_key_encrypted, interdotz_id) FROM stdin;
2	testing3	testing3@interdotz.com	\N	interdotz	testing3	\N	\N	5Sl7T28ytSh3fKeYC2K1HTczuyDFoPis5gV8VnjH2Pe7zgDc0xvWag9EJ82f	2026-04-07 18:18:49	2026-04-07 18:18:49	eyJpdiI6Im5JN1IzdnhYUnlQNDhmN1pMRG10bGc9PSIsInZhbHVlIjoiOFJzV0FKOTVJN1B4T1p6VXV1MWFBZHRXVGIwcHArMlRtVGJuNHJLY3RoZURhalYxeVV6Q09qRnFyRVlyRTZRem50RFpRV3doa2s3MWdUU3FOUktxMlczSmxtSWF5dE9XUHZhVG05ZGJjZkk0VEZTOUh2b2hJVDMwbENabVZHZXhUbjhBUytVbU1IUjNJVEJKcEZ3NDBseU9VclBxZ05jTzlQZVUzYys3QXBrZElrZWN4cGk0QzAyV2FPNjcvWFBzRndpRFRNdWM5UC92WjBwRm5kbEVWVGtQaGNUZkhYRzdRc3E0dFl2M3VtTXdMbDlpdkFLR3NOZlU0MmF4MngxVUN5OTFseDI3bytVRVlNenY2TUZ2dDBYVVNBSzhidGU5aEYxb1BlRUVZK2dpUkdNN1ZnNnQrazlBYUh3UnF3a3kiLCJtYWMiOiI3ZTViOTQyYTA5MzVkZDYxZWQ5ZWJiYTNlMDA0MDU3ZjAyNDQ1YmI1OWI3NzEzMWIxM2Y4YWQ2NWRiZjk5NmFlIiwidGFnIjoiIn0=	eyJpdiI6ImExYTZiNU1INGkwbzQ1SlBxdWxCV2c9PSIsInZhbHVlIjoiKzFCTjVDK3lPL05MUjB1U3lmUWZ0UCttZXhyYjN0ait0cFB6a1lBVjcyRXhIU1UrdHRnN1ptd1FDWk9lMVRsd2ZORHdlc3ZUeUxCcVl3VmVDTmNPQll4Sy9HaTIyVEFQTVdXVTVlSHI2OVJLQ3JmQ21yZVlDSTVmVDhUcXJyTG0vM200c2lYdVBwb0txTThobUwvbFVGdnNoNG1wT0FqV0QxTm1NUUZKOExkTThwSFZ0OTRqWlVWYm5VMC93MzNVRzVuYVFkU21ucEN3R0pRMkhaT0dKQXBpZGRGWW5sdXcyOXBuRmpkQWdkc092T041dTNSak5WVTJva0djcmFrRy95TFhUSFA2c3BYbTR4TWhvS3FaY1kyRmNSRG1vOCtLd1B0M2VCa1NWN1dSRWF6TlB4L3dkVWJ3OTFCYWE5WTkiLCJtYWMiOiJjOTMyODZlZDAyOTJkMDFkYzgyNjQ4OWY3ZTJlNzQzZmQ3NDAzNWQwY2VlMDk3OTE3YzQ4Y2Q2YjAxM2ZlNTc3IiwidGFnIjoiIn0=	2026-04-07 19:18:47	\N	\N
4	muhammad_saman77	muhammad_saman77@interdotz.com	\N	interdotz	muhammad_saman77	\N	\N	fUa6JiX0qbt91PhR6EGjmQ9uyACvUahYSfXlci1zbcA4TUEaZFk6HqGtsPUM	2026-04-11 16:54:13	2026-04-11 16:54:13	eyJpdiI6IlRSbWd6V2VIZ1hYZkkxWWdUb0E2MWc9PSIsInZhbHVlIjoidFIvYjdNSW5Ib09JOERmdDM0OEUranJvQTJyVVpJaUlGcmpkVU05WStGVlRZQnVtNzFNV3Z2ZytxSk1LSWp2NWkwQ2o5VzJPYjN3Y3VZc2tQU2hRbDdtb29vNU84bHU5YlRiWUlReGdQR3ZLcndNMkJzMVRPMEpmL2k5SkJjQW5aQWkySXVNb2xFWmg3M2llaGdBbTVuaitlU0EzY1pDRk9BZ2w5UHFXa3VHa1RkUmUzUjVTaFJyNXZpc3J5TUUwWmkyVnJqQWYzZ3RLZG5MUVFJdDZHSDVDd014c0kyamVReFNrcWRIY0EzNndua0dDSzRCMFhwd2pncmFTZmZtclVRaU84SHk0cVFFL2JXWHpSS3BmVGRvemFvYldGdlloSzYvTUswY0ZSU3pzczNWWHprbnZNaG5nU1lKMjQ2RzI3ZUhXbHFDNmcydVhiMDhWYmFGcGpBPT0iLCJtYWMiOiJhNjc0YTczOGUwM2FmODA3NmU2NjBhMDJmMWM5ZjZhYjYyNDIzYTliODFhMmQ2ZWNhY2MzNjhmZjZiOTNiZThkIiwidGFnIjoiIn0=	eyJpdiI6IjgwWllUdXltZDE0bG54VkhBK3ZSTnc9PSIsInZhbHVlIjoiaEpaVEZKNkFiV1FtRnZXend3azUxUXJZbGdlVTNwUHVvOVYrUGFyajRQT3gveDQxMVVES3lKc2xlbWxwb240N3l1TmxlRVp6ekdYbngrUmRVdlh1RGxOTG92SXphSGU2eE5obHJpTFh3aFYxdCtYZlREcFhHeDFxVElTd3BKVTY1bFp2TGM4NWZUZEJQTTFJcnJJL2Z3ZEFzV1VFUUZxbnAvVzRGNktCL3htbXh2Vkd1ZWVsWVl6MmhNd1daU28rUnpKeUFUaXJ0U0xuWWREUGpTODFHZmYyZjBDMGNVdlBKYzBiN0dSNlF6bG9VaUtLMTAyR1k0akwxYjRsSytHaWVTZzd2SkptVm1obGJPbjJjbXFzakFUOEJoMGd4V1FTNTd3TVNRbFEvN2xSODZtdlAvV3UxZEsxNk1JUGFjQWoydEtlWldvVlhmSjMwZVV6STlyaElnPT0iLCJtYWMiOiIwOWZhNWYyZDljYzExOGQwOWZhOThiNGUyYzc4Zjc5Nzc0OGVjYmFlNzc4NGFjZTQ5MGQ2ZmFiNmM4Njk0ODRmIiwidGFnIjoiIn0=	2026-04-11 17:54:12	\N	\N
3	muhammad_saman78	muhammad_saman78@interdotz.com	\N	interdotz	muhammad_saman78	\N	\N	uQHJf8gvrZ6VrsCFqW7sWYEnRX1OG65noTw0TknDPymH15mHKMsvmXPGetIP	2026-04-11 09:35:35	2026-04-14 10:08:46	eyJpdiI6IlQxVTMyWE1yT2RweUtwUXdiWFdvTEE9PSIsInZhbHVlIjoiUStMQXkrdGhvcVBhcVVGUzJrVU5uSkJkM0xMWUZ5YzlodXY3OUwwSHZJdm51L2JPQ1UxWEtwaDkzS1VpRm1neTl2RnNYSUtpNkt0aFpObTdiclJoNUFkZVFCVHk1R1htMXFHVCtxMm44SHdYdElYanF6b3RhK3pRSU1zZ01zZmxqMzRBTTgzN1VQNHh5VHVYTldjbysrOHN0V29xZXp1c240bFJXNi85czkxcVlrWGZyK2o1ZkJjbXp1NHh1UUxLZmxhazJxak9VUzFFanFJc1hLaGY1TjFla2lrTkxXdkg2OFJkRjh4dE5mdjJ1K1MzUXRLTG9YWHYyZW5rZ0t2YmlBV21FeFVpTEw5MWVnMkoxQUk4L2FzUklYQ094UDlEL2Flc09sZXFmTWRncU5MUDZlNktUVTUybGlucVRXcHVXOE1RZmhubzJMdmp2TnM5T1VCL0NRPT0iLCJtYWMiOiI5Mzc4Yjk2NGI0NWVkODNhMzE1Y2I2N2YzMjBjMTY0Yzg5MTY4MTU0NzIyZmRlMzhjOGQ4MGY5YTcxMTBjMDU2IiwidGFnIjoiIn0=	eyJpdiI6ImFwV283MDNRN0YzQzF5TEtaK0JRNkE9PSIsInZhbHVlIjoiK3BYdTd5YkpsK0hCRzZOR0lBTnJwbGFJbHhybFExODJyY2E0c2w1YmdDWlU2TEY3d1dUc1Q3NGhiWGdyM2o3RG9MUStZa0xBRnJjQ2pneWwrbzVlbnZZY0lGMnlvem90WmNTaW1pZnpJQjgyeHFieXFWM2Q2bldIL1ZaMWU5NHlDbFkrbHpWWk5WeHc5aStHbEg0ZFpENWhpME9BajVMMUcyUStyRElIK0JaY09UcGJDS2UwZzZhZzdHazFFVGJ5WXp0cmJGZzVjYnhHRVJJN2xWQkNPak5WZTVOWEJFdXpFTThTc1ltQjFMTjhEbTBXNVpuMzBYT2VrQUF5TVZlWTNVVFRkY0sxbHF1L3JyVkljMnZJejI4b1pKa2Z6SzdzZnJvUmZwaC9rN051dWVpb3VHNHgyOGhScUlYR0tpT3MrVGJDTG1NMUZZWTFid0JVQ3pudEFBPT0iLCJtYWMiOiI1ZWExODhlYjgxMDY3MTM1NTc4NjI1OGVjZGI3M2IxOWQ4N2JlYzUwZDVmZmI4NWNiM2Y0N2Q0NmNlNWZkNDNkIiwidGFnIjoiIn0=	2026-04-14 10:44:59	\N	01KNS8TFD8B6RYSZ2XX23T3BT5
5	superadmin	superadmin@interdotz.com	\N	interdotz	superadmin	\N	\N	OfoechtNZ5evY2rqWejSwPTEFw8ICFAoWiUvagUmd8kxt0lWjqiGEARe6fjb	2026-04-14 09:36:34	2026-04-14 09:36:34	eyJpdiI6IithZDNaMk9JVE02Mi9PS3N4aEFPMFE9PSIsInZhbHVlIjoieGNhcDFHUVpoZU5RSjdOMi94SDhtdWU4WDRrMndLTjV0bTNMdU5Gam44cEt5cjJKZHFhdlRYT3c2TGo2Tzg1SFl5T1RBZ2tNUllTVElYci9ERGIzc3g3VFpvOEg3c0lDV29Sc01DZURPQlZjdDRrb2dvSW9tRGFSb0MyZS9sQzFUcEppL1pEckJuaGFiZldMSUE2dlVlTEpLWVo4UnBLNnUrandoalBjdXBFaUpGTVowQkJjdUlGSWFWY3FCNWsvTU05aDBld2VYc0R1VEEvZVh4azRMNW1kb3lEOXVXVnRCSmp2Y0hzTUQ0N0Y0R1lUZ0cwWUFJTm11UlN4V0xNRG1lZThFUVNwZitiSGk5dkJHY2NEOEw2NEI2Y3A4WGFPL3JMbjNtTXFkalNFWUUrZXVHUW42TnBDYjBxTm9lSDV1WlA0N2ppNFNYNmV4elVubkZjdGFRPT0iLCJtYWMiOiI3OWIyOWQxYjY4ZWVlMzhmZDg3YTQ0ODgzMWRmNTc0OTlmMDdkZjI4OTA2ZjhlNDFkNDVhMWEwYTFhZmI4ZmI0IiwidGFnIjoiIn0=	eyJpdiI6IldmMTZkZ2dRenR5WTFRejdjNzFhckE9PSIsInZhbHVlIjoiRTV3cTBQL1UxNlRQUit1VzlrQWV5dkFLb1lnL0NXRVBHMkZPdUI5SUM1eTZlaXdFUmtudkNhalF2a1ZsL1kzTzNTSUxKVm8vUnRQaVNuSUJ6QU54WVY3bENOeVdwUStGUlZhN05UTlBnMWFKNEVQbXJmbXc3RS80YUs1U3RaR0J3d2dwQ2pXUkx5b2d5ZktyTFVoQi93Mk5oVlJSdkt4eTNUVFZMMlQzMmdwZm5RL25sbTUvYnVlVjFqYThWVEJnVmJiQ0hQUUI5QWdOM1JNTGkyM1Q3cFdTZ3FRVlNuY2dpaFowZnJvbEJrck5PT2k0UldNT1cvc2I3WnJkTlV1eXdlSzkvejAwa2NzOGFDME9USU1wWllzMEtPWHF0TERIVE81YkJPRzRHajdpMmw5dkhwcWlOQ0M2QXNoY05GaWhsT0h3akNTMUx5UDRrRmRybGpEZEtRPT0iLCJtYWMiOiJlMmVjY2I5ZjE0YWZkN2QwZWFkM2YxMTM1OGEyMjRlMDg4YzZkMmQ3MjE2NTlmYzNkNjE1NDQxNWEwNTM2ZDg5IiwidGFnIjoiIn0=	2026-04-14 10:36:31	\N	\N
1	testing2	testing2@interdotz.com	\N	interdotz	testing2	\N	\N	s4jA5Tyiomiz11TCrN19YKjPhaVG2FfBIMe2tXsdWi22rWpfnzGIMC4oKpXh	2026-04-07 18:14:50	2026-04-14 15:03:30	eyJpdiI6ImtSdUxDdWFHTURWTVBRTjR6N0lTUGc9PSIsInZhbHVlIjoiNlBYQi9heHV3VWZRUUtSYkZZN3FIV1h1TGZNRmo5WndWbzlCSytaank2aXhtakRrMEU2ZG1OQkJ3UWJSL3c2RkV6M2JPaG1jY2pqZVh6enhmb0UzSHNac2lwUDl3L3pySExKVnQxaGd3RjdkSFE3ajdLTjZUVVVLak0zcGYrU1dYUlhVc3JCYjUyMzRkTm9TK09tK0txUEhzbGJrWm9IMW5OeGQ3ZVIyajNzZklOQTkxSFZHdjQ3YVhXUUdvSERBNGt3azJhb1h5LzFvQ2dmbmlST2syZllxaGY4Z3VWTXZBZ05kN2Y1enU1bXFKVkU3d1FsOWhoaFZRQmU3VEdOZm9nem1xWTRvRy9wcEhVUk9TblcrOXlpU0VaZ2xhVHJkOEs0U2llWkZSekYyZlZxamIrV3o5MlZkWnF0QTF2WTciLCJtYWMiOiJhNmMzNmJkNmZhYWVlMWFhZTRhOTNjOWY2NmVhYjI1YTRhNjQwNGQ3NGQ1MmQxZTljOTA0NWU5OTEzNjNjNTY5IiwidGFnIjoiIn0=	eyJpdiI6InRpd2pMNW0wWnpYV09wLzhpMHVVTnc9PSIsInZhbHVlIjoibHJla2xyTTBPODFBRXQ0cm5WKzBzRnd1MXhSNzlzbkxNQTJuUUZBclExakx2N3VIc3ZaTWJWdmdTcS9xMm1MUGxwNi8xU2Q4VENXRG4ydWROLzJQczZZNWFnb1JOK0w0RXBadHlnajNmblY2aGozOEc1dXkyRnNxblh2cEZ0dDZNNHlheTRYclVWdDJGR3RkaU4weisyY05SSHRMbkZ3cTNlbExIWitoV29jQjdwZllDQUJZNDErSGpPVTJwWmJtbEEvUHhqS0Q3ZHR5MldhL0hBcGRETUY0VDVOWXFGT3BnY0Y2bEFVNHhMZHJvdlg4TFQ3VzFNUWJ2YTh6aDY0UFFqNlZhdlVZM1ZxV25nNUw4aGhCL3NBQTlYS3Z5N1p4YzZFTU1RK3k2TFJHSlp3aGI3elJkT1lGMktwamY0M3AiLCJtYWMiOiJkMDAyMzZkODg1YmRjYTg0NDdmNjI2ZmIyMTA2NTIyYmFhMmRmNjc4Zjc4ZGZiZTBiMWI5OGE2ODllMjY3OTRhIiwidGFnIjoiIn0=	2026-04-14 16:03:30	\N	01KNMDEGVRS4XCAWTFNBFKVFNP
6	testing1	testing1@interdotz.com	\N	interdotz	testing1	\N	\N	TEWbMCE85rT6x1yUMZAO5zk5yBCoWn87MzJ8cflmyqMjRPR2kQQz2PnXMVNp	2026-04-14 17:46:39	2026-04-14 18:40:08	eyJpdiI6IlBMYU5DUmxZTk5jL2VxRGFld05pSFE9PSIsInZhbHVlIjoiRlhoRzcraXNQdk5YblNpWStHb0ZWZkQwLzFMWGxhRk8yK255MVNNZnhNOXhKZ1k2elpUa05TaU5adVlOWStSMXA0QXB3UjllVmhPbTAydmlZZTRsVVBGbU50bXlqd2J2QTNjWGlkTDBUbEpHTmY4TnFNTEluUktWRDhldG9kb3BCTGpjNzNCb0NTeVJyOVYyY1FhMTA2TTd1Wm5mK2VPMHZ6bmVuclRxeHA2Tnd3TWI1YytjRkpsNWNQOUZWcEQ0Qmt5SW1TU2puV3QyK1pEY1FYUXpocEZCYytnTUcvNlJSLzR3NHViQmFQdklBL2VZVUdjc0NDdkFTNGMyYjRkWlZXY0NxdUttREpKS0ZqZFpkcGVnb2NJODBkNVVwUUY5c2tTT1ZtcjNGWVNLa2xRVTRHeVpCYTZVM3BHa3pBQk0iLCJtYWMiOiI4ZGU1ZWRjYjg0MWI4NDcwY2ZjMWM4YzQzMmE4ZDg2YjY4MjExMTFmZDE3NjJmOTQ0ZWVlY2NjNjA2ZDIxNjNmIiwidGFnIjoiIn0=	eyJpdiI6IjdDa1JkY1ROdXg3ZW1adFNyL083SUE9PSIsInZhbHVlIjoiRUV0c1lYdFVKYjBWVExnckFKMUVjTk5FZWIvamt6S0V0RlpBcDRKR21USjVESlh0NFV6TnJoeUtteUhWelpEWXFTTHdSNWtDWnZ4UHBnNjlaQVVuUkdWMXl0VzZ4WDZXbDRxbEg0YnFoTVhJMWNEbm5KVTYveDRWYnh3dnhTbFNiczJzRnJKVGpOUDVZVTM3Z3pLa1lNRmFBWkVGYVpMVG1SZElZU0dWRlhsNzlqK1hOWEhyaE1oMVErSlBJUCtrNWU5cTFibGUvcVFQOVkwaWNMQ2M2L1k0Z09icllFQWlnZktPSnVlenFLZ2RhZFlZdSs0c2ZPRkgzZGtQVlFrVzN1L0hRUjlXVHpTbU1uNmVVUjM2KzNSdW5KNEJ1MHVtdlRiNGV2WHFOYTNJa1pENmlSSWZPbC9taXVRN3c4UDMiLCJtYWMiOiI3ZjZhYTgyMTg1NzkwMGMzZDMzYmJiNTk5YjU2Mzk0ODVlOTE5Mzc3NDNiMWIxNTE1Mzc1NmJlZWVkNzUzMzFhIiwidGFnIjoiIn0=	2026-04-14 19:40:08	\N	01KP6HJZC8XBR9ARPCYCYCCPYJ
8	rapael	rapael@interdotz.com	\N	interdotz	rapael	\N	\N	7NPPMjUe0Ftx0GAmZBqxm3ekfxM4WQkrf8SU4Pz9iZ2WoPFdniIsM3o0tAMy	2026-04-14 21:25:17	2026-04-15 15:20:34	eyJpdiI6Ink1OUhVOXVETU13Z0lWeEJtTXlDK3c9PSIsInZhbHVlIjoiWXkxdW9wcHRFcEM0ZCtBdkc0OUdFT3kxc3RSQ015Z0IxbGpBcXlYQlpLSzV5cFhzNVFyZFVyOEMrSCtBM2NKQlZzcFQ5aytRTFhld3Y1dFZJcEtaMG5FRmhqbm91ck5sMlhEb01sb0RkcUVXQlJPQzk5VHM0Y1VST3VLekNmSnpZcy9FQUZ0MmpGOFRhN2pCelF6a2krclBuTE9OZTc0RzcySE9SQ0NxdWFUUFJNOUxPbXRQdzcwalNJcHd4Qm9WaDNwdEhWdm9QM0dURGpnazJoU0JLbjc4emg1YkhUekFkakR3K2FPdEMxTXdETVk4TlpmcXM0a2R0YzNRWTJ6Ylh1aDQ2YTF1czhjMzYxQ2pYdHRyamdFZU1hSGFidlBWQmVncjBtbnR4L0ZOY3hmR2ZXV2hLcXVrN1FGZ0JZZjUiLCJtYWMiOiI1MTExOWMwZjZhNmY5OTU4MjFhOTQ4NTk4YzVkN2Q2M2JlZTVhMzU3ODgzOWJiOTZmMWZlNTljM2YzY2YwMzU0IiwidGFnIjoiIn0=	eyJpdiI6IjcvUmhMbmhmaWxtOFVOeDErOGtuOUE9PSIsInZhbHVlIjoiWWtjbXNqWUhvU2dNUWVqc0NYYUdJb2Q3cGNDMTR4ZVdOOVd4TjhYQWRaZXlYWkJISU1QSFJhdkQ0eHhFZjJPcTZCOWFlYlphZEwxS2M5ODQ1SlA4NzhpZ3FYbVlrbm5EUVlPcDBPYTVtZkNBc3czd1lVMnVMTFVkSFpabVpxaitTd0tzQ2RCd1ZGNUlKUHJCbDNMM0tENm94Nml4bjlqcDFua2t1Z1pmRGF4cWZoRVJESzFiSVFJUFdlOHZMK1pnRGNPOXZyYmU1RTlOZVNWV1htNjlnWVQwV0o5SHRzbWdXSHBTQk9VNVU0Z3I0MXFEdnpZL0ZtL2R2VlJha2FYQnJFN3orQ2hCb1c3b2cwcnB2MXhadEtlbUlJMHhLNTlaUXVxQXc2bkVtRkphb3dtMk9lQW1WeVBsK0FFNGJDaEwiLCJtYWMiOiIyZjQ0NWNiNTExZDlkYjg4MzdjNmE3NDY5YTk4NTM2NjJkY2Y4MmIzOTgxZDU1N2E3OTA3ZjgyMjNiOTY0ODFmIiwidGFnIjoiIn0=	2026-04-15 16:20:33	\N	01KP6W48TK520P1SAB8VVH6B7T
7	noel	noel@interdotz.com	\N	interdotz	noel	\N	\N	OZVvbLFqTgbk8BBvurJradfLRJ4iIPYsvM4L6LKZB5AEN5jVJqPpdeEjT3Ux	2026-04-14 18:43:06	2026-04-15 08:19:24	eyJpdiI6InBiV2tUQjNMdlNwSWM2M0IxbEtaU3c9PSIsInZhbHVlIjoid0RRdnMrN3JXc2ZEYlUwRnRrKzNZVEV2eFFFQ0VnRjc3K0R4SlpEMHp2ZnY0enRsc2xDVlNhM0RJZWx1TEtoTnlQWHdlMEw5enljOXUyQUNtdW5FVExHVVJaOE4rdytVZktENHRFTXZPVkQ0Q2pKZWVnN1A3YUlVQnFMbEFvNDVHZHlJdVk4ZWYzeCtmRzlRT2lhU05Uemw4VDJkMExuYzRsUHd4VS9JbHV1eVZlVlpMK25MU1d4L3J4SGRKNjRWOTFMb3ltV2dnTWgydE05N08zZkhBRVNvNXA4ck5rZzQrak9EN0lPN1I2b3pPenZreTBRejlZLzdSWkM3M1krcER6bG5ORVpIQ3l2Tk9pNUlsdHNnWlFNV2kxWi9Jd2x6Um5BOE40UHk4Z0U9IiwibWFjIjoiMDhhMTk0YmU5MTI4MmZkZDRjYmFlM2UwMmU5MzQwMWM2MTYwYjJkZjljYjU4NDkyNjhmYjIwM2U4YmUxYmNjZiIsInRhZyI6IiJ9	eyJpdiI6IjYreE1rQkE3SHc4MlFhZVhMZHNYK3c9PSIsInZhbHVlIjoiemE0SmVlZTR4UUYwUCt2ZC9kK1luM0NrUnVaQzBocDk1WXRwd1dGdy8wbFhHRDl0MEJhN2VISVJZYmErZFFWNFAwWk0zQjFtV1FrUDUrL0xqcmJkbXBjT2F0cDg2QU5zbHJ3ZC9aZGo2dlliR3FvTG9ad0NlVXZWT0szcEw2K0Fla2h1cHJoK1JKTTJuQ2VTSUd6UVRsWVNlTzZJdFhxYUdLb09Zdk01SUpNd3Z4cUhqRW1LbERib2V6M0YwNGk5MWMzbkJOQjc4Q2VjbmhmQTA5NGpKUUgvcWRUck0yblFmd3o2SFBwQUQxZDIvbzBUMUt3WnRPQ0ZuMWVXQzU0dk5kNmhpaDN3M2JIUTRZVzk5WWUwR0tXdDI0Z3RQbGtOc3A4Zml1VHowWms9IiwibWFjIjoiMjU3MjVlMjU5MDFmNTBkNmQyODgxZTZhMGM3YmM3M2VhYzQ1Yzc3YTQ5YjgwNDNiMjc4M2ZkNjQ0MTAxYWM1OCIsInRhZyI6IiJ9	2026-04-15 09:19:23	\N	01KP6MVJ4DG2ANARCG0W60XERE
\.


--
-- Name: ai_connections_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.ai_connections_id_seq', 1, false);


--
-- Name: approvals_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.approvals_id_seq', 1, false);


--
-- Name: audit_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.audit_logs_id_seq', 104, true);


--
-- Name: chat_message_queues_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.chat_message_queues_id_seq', 29, true);


--
-- Name: exports_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.exports_id_seq', 1, true);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: group_backups_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.group_backups_id_seq', 1, true);


--
-- Name: group_members_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.group_members_id_seq', 11, true);


--
-- Name: group_token_contributions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.group_token_contributions_id_seq', 11, true);


--
-- Name: group_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.group_tokens_id_seq', 7, true);


--
-- Name: groups_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.groups_id_seq', 35, true);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: message_versions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.message_versions_id_seq', 1, true);


--
-- Name: messages_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.messages_id_seq', 68, true);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.migrations_id_seq', 20, true);


--
-- Name: pending_payments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.pending_payments_id_seq', 1, false);


--
-- Name: permissions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.permissions_id_seq', 1, false);


--
-- Name: poll_votes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.poll_votes_id_seq', 1, false);


--
-- Name: recovery_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.recovery_logs_id_seq', 1, false);


--
-- Name: role_permissions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.role_permissions_id_seq', 1, false);


--
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.roles_id_seq', 3, true);


--
-- Name: subscription_payments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.subscription_payments_id_seq', 1, true);


--
-- Name: subscription_seats_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.subscription_seats_id_seq', 1, false);


--
-- Name: subscriptions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.subscriptions_id_seq', 7, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: normchat
--

SELECT pg_catalog.setval('public.users_id_seq', 8, true);


--
-- Name: ai_connections ai_connections_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.ai_connections
    ADD CONSTRAINT ai_connections_pkey PRIMARY KEY (id);


--
-- Name: ai_connections ai_connections_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.ai_connections
    ADD CONSTRAINT ai_connections_user_id_unique UNIQUE (user_id);


--
-- Name: approvals approvals_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.approvals
    ADD CONSTRAINT approvals_pkey PRIMARY KEY (id);


--
-- Name: audit_logs audit_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: chat_message_queues chat_message_queues_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.chat_message_queues
    ADD CONSTRAINT chat_message_queues_pkey PRIMARY KEY (id);


--
-- Name: exports exports_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.exports
    ADD CONSTRAINT exports_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: group_backups group_backups_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.group_backups
    ADD CONSTRAINT group_backups_pkey PRIMARY KEY (id);


--
-- Name: group_members group_members_group_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.group_members
    ADD CONSTRAINT group_members_group_id_user_id_unique UNIQUE (group_id, user_id);


--
-- Name: group_members group_members_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.group_members
    ADD CONSTRAINT group_members_pkey PRIMARY KEY (id);


--
-- Name: group_token_contributions group_token_contributions_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.group_token_contributions
    ADD CONSTRAINT group_token_contributions_pkey PRIMARY KEY (id);


--
-- Name: group_tokens group_tokens_group_id_unique; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.group_tokens
    ADD CONSTRAINT group_tokens_group_id_unique UNIQUE (group_id);


--
-- Name: group_tokens group_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.group_tokens
    ADD CONSTRAINT group_tokens_pkey PRIMARY KEY (id);


--
-- Name: groups groups_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.groups
    ADD CONSTRAINT groups_pkey PRIMARY KEY (id);


--
-- Name: groups groups_share_id_unique; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.groups
    ADD CONSTRAINT groups_share_id_unique UNIQUE (share_id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: message_versions message_versions_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.message_versions
    ADD CONSTRAINT message_versions_pkey PRIMARY KEY (id);


--
-- Name: messages messages_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.messages
    ADD CONSTRAINT messages_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: pending_payments pending_payments_order_id_unique; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.pending_payments
    ADD CONSTRAINT pending_payments_order_id_unique UNIQUE (order_id);


--
-- Name: pending_payments pending_payments_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.pending_payments
    ADD CONSTRAINT pending_payments_pkey PRIMARY KEY (id);


--
-- Name: permissions permissions_key_unique; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_key_unique UNIQUE (key);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: poll_votes poll_votes_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.poll_votes
    ADD CONSTRAINT poll_votes_pkey PRIMARY KEY (id);


--
-- Name: poll_votes poll_votes_poll_message_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.poll_votes
    ADD CONSTRAINT poll_votes_poll_message_id_user_id_unique UNIQUE (poll_message_id, user_id);


--
-- Name: recovery_logs recovery_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.recovery_logs
    ADD CONSTRAINT recovery_logs_pkey PRIMARY KEY (id);


--
-- Name: role_permissions role_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.role_permissions
    ADD CONSTRAINT role_permissions_pkey PRIMARY KEY (id);


--
-- Name: role_permissions role_permissions_role_id_permission_id_unique; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.role_permissions
    ADD CONSTRAINT role_permissions_role_id_permission_id_unique UNIQUE (role_id, permission_id);


--
-- Name: roles roles_key_unique; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_key_unique UNIQUE (key);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: subscription_payments subscription_payments_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.subscription_payments
    ADD CONSTRAINT subscription_payments_pkey PRIMARY KEY (id);


--
-- Name: subscription_payments subscription_payments_reference_unique; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.subscription_payments
    ADD CONSTRAINT subscription_payments_reference_unique UNIQUE (reference);


--
-- Name: subscription_seats subscription_seats_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.subscription_seats
    ADD CONSTRAINT subscription_seats_pkey PRIMARY KEY (id);


--
-- Name: subscription_seats subscription_seats_subscription_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.subscription_seats
    ADD CONSTRAINT subscription_seats_subscription_id_user_id_unique UNIQUE (subscription_id, user_id);


--
-- Name: subscriptions subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_pkey PRIMARY KEY (id);


--
-- Name: users users_auth_provider_provider_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_auth_provider_provider_user_id_unique UNIQUE (auth_provider, provider_user_id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: normchat
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: normchat
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: chat_message_queues_group_id_status_id_index; Type: INDEX; Schema: public; Owner: normchat
--

CREATE INDEX chat_message_queues_group_id_status_id_index ON public.chat_message_queues USING btree (group_id, status, id);


--
-- Name: group_members_read_state_idx; Type: INDEX; Schema: public; Owner: normchat
--

CREATE INDEX group_members_read_state_idx ON public.group_members USING btree (group_id, user_id, last_read_message_id);


--
-- Name: group_token_contributions_group_id_source_index; Type: INDEX; Schema: public; Owner: normchat
--

CREATE INDEX group_token_contributions_group_id_source_index ON public.group_token_contributions USING btree (group_id, source);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: normchat
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: messages_group_id_created_at_index; Type: INDEX; Schema: public; Owner: normchat
--

CREATE INDEX messages_group_id_created_at_index ON public.messages USING btree (group_id, created_at);


--
-- Name: messages_group_id_message_type_index; Type: INDEX; Schema: public; Owner: normchat
--

CREATE INDEX messages_group_id_message_type_index ON public.messages USING btree (group_id, message_type);


--
-- Name: messages_group_id_reply_to_message_id_index; Type: INDEX; Schema: public; Owner: normchat
--

CREATE INDEX messages_group_id_reply_to_message_id_index ON public.messages USING btree (group_id, reply_to_message_id);


--
-- Name: pending_payments_status_order_id_index; Type: INDEX; Schema: public; Owner: normchat
--

CREATE INDEX pending_payments_status_order_id_index ON public.pending_payments USING btree (status, order_id);


--
-- Name: pending_payments_user_id_status_index; Type: INDEX; Schema: public; Owner: normchat
--

CREATE INDEX pending_payments_user_id_status_index ON public.pending_payments USING btree (user_id, status);


--
-- Name: poll_votes_group_id_poll_message_id_index; Type: INDEX; Schema: public; Owner: normchat
--

CREATE INDEX poll_votes_group_id_poll_message_id_index ON public.poll_votes USING btree (group_id, poll_message_id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: normchat
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: normchat
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: subscription_payments_group_id_created_at_index; Type: INDEX; Schema: public; Owner: normchat
--

CREATE INDEX subscription_payments_group_id_created_at_index ON public.subscription_payments USING btree (group_id, created_at);


--
-- Name: ai_connections ai_connections_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.ai_connections
    ADD CONSTRAINT ai_connections_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: approvals approvals_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.approvals
    ADD CONSTRAINT approvals_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: approvals approvals_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.approvals
    ADD CONSTRAINT approvals_group_id_foreign FOREIGN KEY (group_id) REFERENCES public.groups(id) ON DELETE CASCADE;


--
-- Name: approvals approvals_rejected_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.approvals
    ADD CONSTRAINT approvals_rejected_by_foreign FOREIGN KEY (rejected_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: approvals approvals_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.approvals
    ADD CONSTRAINT approvals_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: audit_logs audit_logs_actor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_actor_id_foreign FOREIGN KEY (actor_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: audit_logs audit_logs_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_group_id_foreign FOREIGN KEY (group_id) REFERENCES public.groups(id) ON DELETE SET NULL;


--
-- Name: chat_message_queues chat_message_queues_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.chat_message_queues
    ADD CONSTRAINT chat_message_queues_group_id_foreign FOREIGN KEY (group_id) REFERENCES public.groups(id) ON DELETE CASCADE;


--
-- Name: chat_message_queues chat_message_queues_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.chat_message_queues
    ADD CONSTRAINT chat_message_queues_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.messages(id) ON DELETE CASCADE;


--
-- Name: exports exports_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.exports
    ADD CONSTRAINT exports_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: exports exports_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.exports
    ADD CONSTRAINT exports_group_id_foreign FOREIGN KEY (group_id) REFERENCES public.groups(id) ON DELETE CASCADE;


--
-- Name: group_backups group_backups_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.group_backups
    ADD CONSTRAINT group_backups_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: group_backups group_backups_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.group_backups
    ADD CONSTRAINT group_backups_group_id_foreign FOREIGN KEY (group_id) REFERENCES public.groups(id) ON DELETE CASCADE;


--
-- Name: group_members group_members_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.group_members
    ADD CONSTRAINT group_members_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: group_members group_members_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.group_members
    ADD CONSTRAINT group_members_group_id_foreign FOREIGN KEY (group_id) REFERENCES public.groups(id) ON DELETE CASCADE;


--
-- Name: group_members group_members_invited_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.group_members
    ADD CONSTRAINT group_members_invited_by_foreign FOREIGN KEY (invited_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: group_members group_members_last_read_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.group_members
    ADD CONSTRAINT group_members_last_read_message_id_foreign FOREIGN KEY (last_read_message_id) REFERENCES public.messages(id) ON DELETE SET NULL;


--
-- Name: group_members group_members_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.group_members
    ADD CONSTRAINT group_members_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: group_members group_members_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.group_members
    ADD CONSTRAINT group_members_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: group_token_contributions group_token_contributions_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.group_token_contributions
    ADD CONSTRAINT group_token_contributions_group_id_foreign FOREIGN KEY (group_id) REFERENCES public.groups(id) ON DELETE CASCADE;


--
-- Name: group_token_contributions group_token_contributions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.group_token_contributions
    ADD CONSTRAINT group_token_contributions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: group_tokens group_tokens_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.group_tokens
    ADD CONSTRAINT group_tokens_group_id_foreign FOREIGN KEY (group_id) REFERENCES public.groups(id) ON DELETE CASCADE;


--
-- Name: groups groups_owner_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.groups
    ADD CONSTRAINT groups_owner_id_foreign FOREIGN KEY (owner_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: message_versions message_versions_edited_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.message_versions
    ADD CONSTRAINT message_versions_edited_by_foreign FOREIGN KEY (edited_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: message_versions message_versions_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.message_versions
    ADD CONSTRAINT message_versions_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.messages(id) ON DELETE CASCADE;


--
-- Name: messages messages_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.messages
    ADD CONSTRAINT messages_group_id_foreign FOREIGN KEY (group_id) REFERENCES public.groups(id) ON DELETE CASCADE;


--
-- Name: messages messages_reply_to_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.messages
    ADD CONSTRAINT messages_reply_to_message_id_foreign FOREIGN KEY (reply_to_message_id) REFERENCES public.messages(id) ON DELETE SET NULL;


--
-- Name: pending_payments pending_payments_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.pending_payments
    ADD CONSTRAINT pending_payments_group_id_foreign FOREIGN KEY (group_id) REFERENCES public.groups(id) ON DELETE SET NULL;


--
-- Name: pending_payments pending_payments_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.pending_payments
    ADD CONSTRAINT pending_payments_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: poll_votes poll_votes_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.poll_votes
    ADD CONSTRAINT poll_votes_group_id_foreign FOREIGN KEY (group_id) REFERENCES public.groups(id) ON DELETE CASCADE;


--
-- Name: poll_votes poll_votes_poll_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.poll_votes
    ADD CONSTRAINT poll_votes_poll_message_id_foreign FOREIGN KEY (poll_message_id) REFERENCES public.messages(id) ON DELETE CASCADE;


--
-- Name: poll_votes poll_votes_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.poll_votes
    ADD CONSTRAINT poll_votes_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: recovery_logs recovery_logs_backup_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.recovery_logs
    ADD CONSTRAINT recovery_logs_backup_id_foreign FOREIGN KEY (backup_id) REFERENCES public.group_backups(id) ON DELETE CASCADE;


--
-- Name: recovery_logs recovery_logs_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.recovery_logs
    ADD CONSTRAINT recovery_logs_group_id_foreign FOREIGN KEY (group_id) REFERENCES public.groups(id) ON DELETE CASCADE;


--
-- Name: recovery_logs recovery_logs_restored_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.recovery_logs
    ADD CONSTRAINT recovery_logs_restored_by_foreign FOREIGN KEY (restored_by) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: role_permissions role_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.role_permissions
    ADD CONSTRAINT role_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: role_permissions role_permissions_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.role_permissions
    ADD CONSTRAINT role_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: subscription_payments subscription_payments_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.subscription_payments
    ADD CONSTRAINT subscription_payments_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: subscription_payments subscription_payments_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.subscription_payments
    ADD CONSTRAINT subscription_payments_group_id_foreign FOREIGN KEY (group_id) REFERENCES public.groups(id) ON DELETE CASCADE;


--
-- Name: subscription_payments subscription_payments_subscription_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.subscription_payments
    ADD CONSTRAINT subscription_payments_subscription_id_foreign FOREIGN KEY (subscription_id) REFERENCES public.subscriptions(id) ON DELETE CASCADE;


--
-- Name: subscription_seats subscription_seats_subscription_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.subscription_seats
    ADD CONSTRAINT subscription_seats_subscription_id_foreign FOREIGN KEY (subscription_id) REFERENCES public.subscriptions(id) ON DELETE CASCADE;


--
-- Name: subscription_seats subscription_seats_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.subscription_seats
    ADD CONSTRAINT subscription_seats_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: subscriptions subscriptions_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: normchat
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_group_id_foreign FOREIGN KEY (group_id) REFERENCES public.groups(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict v3xxq9GWm165cv305TSqhtbbdYcHshz5QfOcbAF8NmyQSEmudKv78cCWPOz1Lc5

