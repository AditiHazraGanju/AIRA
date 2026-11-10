-- =============================================================================
-- Aira: full database schema (PostgreSQL 15+ with the pgvector extension)
--
-- Creates the 27 tables the Aira API uses, with their sequences, indexes and
-- foreign keys, plus the two default ht_settings rows. Run it once on a new,
-- empty database:
--
--   createdb aira_db
--   psql -d aira_db -v ON_ERROR_STOP=1 -f database/sql/aira_schema.sql
--   php artisan db:seed            # the 36 states for the registration form
--
-- The pgvector extension must be installed on the server
-- (https://github.com/pgvector/pgvector). CREATE EXTENSION needs a superuser
-- or a database owner, depending on how pgvector was installed.
--
-- Tables the API does not use, kept so the database matches the cleaned one:
-- roles, permissions, role_has_permissions, model_has_roles,
-- model_has_permissions, password_resets.
-- =============================================================================

BEGIN;

CREATE EXTENSION IF NOT EXISTS vector WITH SCHEMA public;

CREATE TYPE public.bu_type_enum AS ENUM (
    'Primary(Self)',
    'Secondary(Family Member)'
);

-- permissions.id takes its default from this sequence, which no column owns,
-- so pg_dump of the table alone leaves it out.
CREATE SEQUENCE public.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

--
--

\restrict 6sipGN29c8pVB5h6q3Rj0v27J9nRp1sC9dlMdWkAO6chPivSaz697wCea6CHC67

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

--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);

--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);

--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
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

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;

--
-- Name: ht_content_chunks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ht_content_chunks (
    chunk_id bigint NOT NULL,
    content_id bigint NOT NULL,
    chunk_index integer NOT NULL,
    chunk_text text NOT NULL,
    embedding public.vector(768),
    created_on timestamp with time zone DEFAULT now() NOT NULL
);

--
-- Name: ht_content_chunks_chunk_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ht_content_chunks_chunk_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

--
-- Name: ht_content_chunks_chunk_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ht_content_chunks_chunk_id_seq OWNED BY public.ht_content_chunks.chunk_id;

--
-- Name: ht_content_item_purpose; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ht_content_item_purpose (
    content_id bigint NOT NULL,
    purpose_id bigint NOT NULL
);

--
-- Name: ht_content_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ht_content_items (
    content_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    content_type character varying(10) NOT NULL,
    body text,
    file_path character varying(500),
    file_name character varying(255),
    index_status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    index_error text,
    indexed_on timestamp with time zone,
    created_on timestamp with time zone DEFAULT now() NOT NULL,
    created_by bigint,
    modified_on timestamp with time zone,
    modified_by bigint,
    is_active boolean DEFAULT true NOT NULL,
    is_deleted boolean DEFAULT false NOT NULL,
    deleted_on timestamp with time zone,
    deleted_by bigint,
    CONSTRAINT ck_ht_content_items_index_status CHECK (((index_status)::text = ANY (ARRAY[('pending'::character varying)::text, ('processing'::character varying)::text, ('indexed'::character varying)::text, ('failed'::character varying)::text]))),
    CONSTRAINT ht_content_items_content_type_check CHECK (((content_type)::text = ANY (ARRAY[('text'::character varying)::text, ('pdf'::character varying)::text])))
);

--
-- Name: ht_content_items_content_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ht_content_items_content_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

--
-- Name: ht_content_items_content_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ht_content_items_content_id_seq OWNED BY public.ht_content_items.content_id;

--
-- Name: ht_content_purposes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ht_content_purposes (
    purpose_id bigint NOT NULL,
    name character varying(150) NOT NULL,
    parent_id bigint,
    created_on timestamp with time zone DEFAULT now() NOT NULL,
    created_by bigint,
    modified_on timestamp with time zone,
    modified_by bigint,
    is_active boolean DEFAULT true NOT NULL,
    is_deleted boolean DEFAULT false NOT NULL,
    deleted_on timestamp with time zone,
    deleted_by bigint
);

--
-- Name: ht_content_purposes_purpose_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ht_content_purposes_purpose_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

--
-- Name: ht_content_purposes_purpose_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ht_content_purposes_purpose_id_seq OWNED BY public.ht_content_purposes.purpose_id;

--
-- Name: ht_guardrails; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ht_guardrails (
    guardrail_id bigint NOT NULL,
    rule_text text NOT NULL,
    created_on timestamp with time zone DEFAULT now() NOT NULL,
    created_by bigint,
    modified_on timestamp with time zone,
    modified_by bigint,
    is_active boolean DEFAULT true NOT NULL,
    is_deleted boolean DEFAULT false NOT NULL,
    deleted_on timestamp with time zone,
    deleted_by bigint
);

--
-- Name: ht_guardrails_guardrail_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ht_guardrails_guardrail_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

--
-- Name: ht_guardrails_guardrail_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ht_guardrails_guardrail_id_seq OWNED BY public.ht_guardrails.guardrail_id;

--
-- Name: ht_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ht_messages (
    message_id bigint NOT NULL,
    session_id bigint NOT NULL,
    seq integer NOT NULL,
    role character varying(10) NOT NULL,
    content text NOT NULL,
    input_mode character varying(5),
    interrupted boolean DEFAULT false NOT NULL,
    metadata jsonb,
    created_on timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT ht_messages_input_mode_check CHECK (((input_mode)::text = ANY (ARRAY[('voice'::character varying)::text, ('text'::character varying)::text]))),
    CONSTRAINT ht_messages_role_check CHECK (((role)::text = ANY (ARRAY[('user'::character varying)::text, ('assistant'::character varying)::text, ('tool'::character varying)::text])))
);

--
-- Name: ht_messages_message_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ht_messages_message_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

--
-- Name: ht_messages_message_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ht_messages_message_id_seq OWNED BY public.ht_messages.message_id;

--
-- Name: ht_qa_pairs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ht_qa_pairs (
    qa_id bigint NOT NULL,
    session_id bigint NOT NULL,
    seq integer NOT NULL,
    question text NOT NULL,
    answer text NOT NULL,
    feedback character varying(10),
    feedback_on timestamp with time zone,
    created_on timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT ht_qa_pairs_feedback_check CHECK (((feedback)::text = ANY (ARRAY[('approved'::character varying)::text, ('rejected'::character varying)::text])))
);

--
-- Name: ht_qa_pairs_qa_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ht_qa_pairs_qa_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

--
-- Name: ht_qa_pairs_qa_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ht_qa_pairs_qa_id_seq OWNED BY public.ht_qa_pairs.qa_id;

--
-- Name: ht_sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ht_sessions (
    session_id bigint NOT NULL,
    session_uuid uuid NOT NULL,
    sh_user_id bigint NOT NULL,
    language character varying(3) NOT NULL,
    status character varying(10) DEFAULT 'active'::character varying NOT NULL,
    started_on timestamp with time zone DEFAULT now() NOT NULL,
    last_activity_on timestamp with time zone DEFAULT now() NOT NULL,
    ended_on timestamp with time zone,
    end_reason character varying(50),
    turn_count integer DEFAULT 0 NOT NULL,
    title character varying(255),
    summary text,
    tags jsonb,
    summary_status character varying(10) DEFAULT 'pending'::character varying NOT NULL,
    summary_error text,
    rating smallint,
    rated_on timestamp with time zone,
    created_on timestamp with time zone DEFAULT now() NOT NULL,
    modified_on timestamp with time zone,
    is_deleted boolean DEFAULT false NOT NULL,
    deleted_on timestamp with time zone,
    CONSTRAINT ht_sessions_language_check CHECK (((language)::text = ANY (ARRAY[('ENG'::character varying)::text, ('HIN'::character varying)::text, ('MAR'::character varying)::text, ('BEN'::character varying)::text]))),
    CONSTRAINT ht_sessions_rating_check CHECK (((rating >= 1) AND (rating <= 5))),
    CONSTRAINT ht_sessions_status_check CHECK (((status)::text = ANY (ARRAY[('active'::character varying)::text, ('ended'::character varying)::text]))),
    CONSTRAINT ht_sessions_summary_status_check CHECK (((summary_status)::text = ANY (ARRAY[('pending'::character varying)::text, ('ready'::character varying)::text, ('failed'::character varying)::text, ('skipped'::character varying)::text])))
);

--
-- Name: ht_sessions_session_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ht_sessions_session_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

--
-- Name: ht_sessions_session_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ht_sessions_session_id_seq OWNED BY public.ht_sessions.session_id;

--
-- Name: ht_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ht_settings (
    setting_id bigint NOT NULL,
    setting_key character varying(100) NOT NULL,
    setting_value jsonb DEFAULT '{}'::jsonb NOT NULL,
    modified_on timestamp with time zone,
    modified_by bigint
);

--
-- Name: ht_settings_setting_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ht_settings_setting_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

--
-- Name: ht_settings_setting_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ht_settings_setting_id_seq OWNED BY public.ht_settings.setting_id;

--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
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

--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
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

--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;

--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);

--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;

--
-- Name: model_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_permissions (
    permission_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);

--
-- Name: model_has_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_roles (
    role_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);

--
-- Name: password_resets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_resets (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);

--
-- Name: permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissions (
    id bigint DEFAULT nextval('public.permissions_id_seq'::regclass) NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone DEFAULT now(),
    updated_at timestamp(0) without time zone DEFAULT now()
);

--
-- Name: role_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.role_has_permissions (
    permission_id bigint NOT NULL,
    role_id bigint NOT NULL
);

--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;

--
-- Name: saathi_beneficiary; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.saathi_beneficiary (
    beneficiary_id integer NOT NULL,
    name character varying(100),
    mobile character varying(255),
    is_whatsapp boolean DEFAULT false,
    related_to_beneficiary_id integer,
    address character varying(255),
    aadhar character varying(255),
    pin character varying(10),
    dob date,
    gender character varying(100),
    yearly_income numeric,
    community_id integer,
    bank_details_id integer,
    customer_segregation_tags text,
    sh_user_id integer,
    note text,
    id_number character varying(30),
    monthly_income_range character varying(50),
    created_on timestamp without time zone DEFAULT now(),
    created_by character varying(100),
    modified_on timestamp without time zone DEFAULT now(),
    modified_by character varying(100),
    is_deleted boolean DEFAULT false,
    deleted_on timestamp without time zone,
    deleted_by character varying(100),
    is_active boolean DEFAULT true,
    bu_type public.bu_type_enum DEFAULT 'Primary(Self)'::public.bu_type_enum,
    state_id integer,
    state_name character varying(255),
    city character varying(255),
    landmark character varying(255),
    unique_id character varying(12) NOT NULL
);

--
-- Name: saathi_beneficiary_beneficiary_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.saathi_beneficiary_beneficiary_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

--
-- Name: saathi_beneficiary_beneficiary_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.saathi_beneficiary_beneficiary_id_seq OWNED BY public.saathi_beneficiary.beneficiary_id;

--
-- Name: saathi_community_entrepreneur; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.saathi_community_entrepreneur (
    community_entrepreneur_id integer NOT NULL,
    name character varying(100),
    address character varying(255),
    mobile character varying(255),
    is_whatsapp boolean DEFAULT false,
    dob date,
    registered_on timestamp without time zone DEFAULT now(),
    aadhar character varying(255),
    pan character varying(255),
    email character varying(255),
    gender character varying(100),
    community_id integer,
    bank_details_id integer,
    sh_user_id integer,
    note text,
    device_id character varying(255),
    id_number character varying(30),
    created_on timestamp without time zone DEFAULT now(),
    created_by character varying(100),
    modified_on timestamp without time zone DEFAULT now(),
    modified_by character varying(100),
    is_deleted boolean DEFAULT false,
    deleted_on timestamp without time zone,
    deleted_by character varying(100),
    is_active boolean DEFAULT true
);

--
-- Name: saathi_community_entrepreneur_community_entrepreneur_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.saathi_community_entrepreneur_community_entrepreneur_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

--
-- Name: saathi_community_entrepreneur_community_entrepreneur_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.saathi_community_entrepreneur_community_entrepreneur_id_seq OWNED BY public.saathi_community_entrepreneur.community_entrepreneur_id;

--
-- Name: saathi_login_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.saathi_login_log (
    login_log_id integer NOT NULL,
    mobile_no character varying(255),
    otp integer,
    valid_upto timestamp without time zone,
    retry_count integer,
    login_datetime timestamp without time zone,
    otp_msg_type character varying(100),
    created_on timestamp without time zone DEFAULT now(),
    created_by character varying(100),
    modified_on timestamp without time zone DEFAULT now(),
    modified_by character varying(100),
    is_deleted boolean DEFAULT false,
    deleted_on timestamp without time zone,
    deleted_by character varying(100),
    is_active boolean DEFAULT true
);

--
-- Name: saathi_login_log_login_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.saathi_login_log_login_log_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

--
-- Name: saathi_login_log_login_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.saathi_login_log_login_log_id_seq OWNED BY public.saathi_login_log.login_log_id;

--
-- Name: saathi_states; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.saathi_states (
    state_id integer NOT NULL,
    state_name character varying(100),
    state_code character varying(10),
    is_active boolean DEFAULT true,
    created_on timestamp without time zone DEFAULT now(),
    modified_on timestamp without time zone DEFAULT now()
);

--
-- Name: saathi_states_state_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.saathi_states_state_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

--
-- Name: saathi_states_state_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.saathi_states_state_id_seq OWNED BY public.saathi_states.state_id;

--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text,
    last_activity integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255),
    email character varying(255),
    email_verified_at timestamp(0) without time zone,
    password character varying(255),
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    mobile text,
    age integer,
    gender character varying(50),
    pincode character varying(10),
    created_by bigint,
    modified_by bigint,
    status boolean DEFAULT true,
    is_deleted boolean DEFAULT false,
    deleted_on timestamp without time zone,
    deleted_by bigint,
    auth0_token text,
    preferred_language character varying(255),
    token_expired_at timestamp without time zone,
    auth0_id text,
    dob date,
    monthly_income_range character varying(50),
    id_number character varying(30),
    id_proof_file character varying(255),
    address character varying(500),
    landmark character varying(255),
    state_id integer,
    state_name character varying(255),
    city character varying(255),
    is_tara_user boolean DEFAULT false NOT NULL
);

--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;

--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);

--
-- Name: ht_content_chunks chunk_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_content_chunks ALTER COLUMN chunk_id SET DEFAULT nextval('public.ht_content_chunks_chunk_id_seq'::regclass);

--
-- Name: ht_content_items content_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_content_items ALTER COLUMN content_id SET DEFAULT nextval('public.ht_content_items_content_id_seq'::regclass);

--
-- Name: ht_content_purposes purpose_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_content_purposes ALTER COLUMN purpose_id SET DEFAULT nextval('public.ht_content_purposes_purpose_id_seq'::regclass);

--
-- Name: ht_guardrails guardrail_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_guardrails ALTER COLUMN guardrail_id SET DEFAULT nextval('public.ht_guardrails_guardrail_id_seq'::regclass);

--
-- Name: ht_messages message_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_messages ALTER COLUMN message_id SET DEFAULT nextval('public.ht_messages_message_id_seq'::regclass);

--
-- Name: ht_qa_pairs qa_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_qa_pairs ALTER COLUMN qa_id SET DEFAULT nextval('public.ht_qa_pairs_qa_id_seq'::regclass);

--
-- Name: ht_sessions session_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_sessions ALTER COLUMN session_id SET DEFAULT nextval('public.ht_sessions_session_id_seq'::regclass);

--
-- Name: ht_settings setting_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_settings ALTER COLUMN setting_id SET DEFAULT nextval('public.ht_settings_setting_id_seq'::regclass);

--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);

--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);

--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);

--
-- Name: saathi_beneficiary beneficiary_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.saathi_beneficiary ALTER COLUMN beneficiary_id SET DEFAULT nextval('public.saathi_beneficiary_beneficiary_id_seq'::regclass);

--
-- Name: saathi_community_entrepreneur community_entrepreneur_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.saathi_community_entrepreneur ALTER COLUMN community_entrepreneur_id SET DEFAULT nextval('public.saathi_community_entrepreneur_community_entrepreneur_id_seq'::regclass);

--
-- Name: saathi_login_log login_log_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.saathi_login_log ALTER COLUMN login_log_id SET DEFAULT nextval('public.saathi_login_log_login_log_id_seq'::regclass);

--
-- Name: saathi_states state_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.saathi_states ALTER COLUMN state_id SET DEFAULT nextval('public.saathi_states_state_id_seq'::regclass);

--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);

--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);

--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);

--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);

--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);

--
-- Name: ht_content_chunks ht_content_chunks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_content_chunks
    ADD CONSTRAINT ht_content_chunks_pkey PRIMARY KEY (chunk_id);

--
-- Name: ht_content_item_purpose ht_content_item_purpose_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_content_item_purpose
    ADD CONSTRAINT ht_content_item_purpose_pkey PRIMARY KEY (content_id, purpose_id);

--
-- Name: ht_content_items ht_content_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_content_items
    ADD CONSTRAINT ht_content_items_pkey PRIMARY KEY (content_id);

--
-- Name: ht_content_purposes ht_content_purposes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_content_purposes
    ADD CONSTRAINT ht_content_purposes_pkey PRIMARY KEY (purpose_id);

--
-- Name: ht_guardrails ht_guardrails_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_guardrails
    ADD CONSTRAINT ht_guardrails_pkey PRIMARY KEY (guardrail_id);

--
-- Name: ht_messages ht_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_messages
    ADD CONSTRAINT ht_messages_pkey PRIMARY KEY (message_id);

--
-- Name: ht_qa_pairs ht_qa_pairs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_qa_pairs
    ADD CONSTRAINT ht_qa_pairs_pkey PRIMARY KEY (qa_id);

--
-- Name: ht_sessions ht_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_sessions
    ADD CONSTRAINT ht_sessions_pkey PRIMARY KEY (session_id);

--
-- Name: ht_settings ht_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_settings
    ADD CONSTRAINT ht_settings_pkey PRIMARY KEY (setting_id);

--
-- Name: ht_settings ht_settings_setting_key_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_settings
    ADD CONSTRAINT ht_settings_setting_key_key UNIQUE (setting_key);

--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);

--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);

--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);

--
-- Name: model_has_permissions model_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_pkey PRIMARY KEY (permission_id, model_id, model_type);

--
-- Name: model_has_roles model_has_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_pkey PRIMARY KEY (role_id, model_id, model_type);

--
-- Name: permissions permissions_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_name_guard_name_unique UNIQUE (name, guard_name);

--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);

--
-- Name: role_has_permissions role_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_pkey PRIMARY KEY (permission_id, role_id);

--
-- Name: roles roles_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_name_guard_name_unique UNIQUE (name, guard_name);

--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);

--
-- Name: saathi_beneficiary saathi_beneficiary_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.saathi_beneficiary
    ADD CONSTRAINT saathi_beneficiary_pkey PRIMARY KEY (beneficiary_id);

--
-- Name: saathi_beneficiary saathi_beneficiary_unique_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.saathi_beneficiary
    ADD CONSTRAINT saathi_beneficiary_unique_id_unique UNIQUE (unique_id);

--
-- Name: saathi_community_entrepreneur saathi_community_entrepreneur_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.saathi_community_entrepreneur
    ADD CONSTRAINT saathi_community_entrepreneur_pkey PRIMARY KEY (community_entrepreneur_id);

--
-- Name: saathi_login_log saathi_login_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.saathi_login_log
    ADD CONSTRAINT saathi_login_log_pkey PRIMARY KEY (login_log_id);

--
-- Name: saathi_states saathi_states_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.saathi_states
    ADD CONSTRAINT saathi_states_pkey PRIMARY KEY (state_id);

--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);

--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);

--
-- Name: idx_beneficiary_state_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_beneficiary_state_id ON public.saathi_beneficiary USING btree (state_id);

--
-- Name: ix_ht_content_chunks_content; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_ht_content_chunks_content ON public.ht_content_chunks USING btree (content_id);

--
-- Name: ix_ht_content_chunks_embedding_hnsw; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_ht_content_chunks_embedding_hnsw ON public.ht_content_chunks USING hnsw (embedding public.vector_cosine_ops);

--
-- Name: ix_ht_content_item_purpose_purpose; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_ht_content_item_purpose_purpose ON public.ht_content_item_purpose USING btree (purpose_id);

--
-- Name: ix_ht_content_items_active; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_ht_content_items_active ON public.ht_content_items USING btree (is_active, is_deleted);

--
-- Name: ix_ht_content_purposes_parent; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_ht_content_purposes_parent ON public.ht_content_purposes USING btree (parent_id);

--
-- Name: ix_ht_guardrails_active; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_ht_guardrails_active ON public.ht_guardrails USING btree (is_active, is_deleted);

--
-- Name: ix_ht_qa_pairs_rejected; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_ht_qa_pairs_rejected ON public.ht_qa_pairs USING btree (feedback_on DESC) WHERE ((feedback)::text = 'rejected'::text);

--
-- Name: ix_ht_qa_pairs_session; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_ht_qa_pairs_session ON public.ht_qa_pairs USING btree (session_id, seq);

--
-- Name: ix_ht_sessions_active_activity; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_ht_sessions_active_activity ON public.ht_sessions USING btree (last_activity_on) WHERE ((status)::text = 'active'::text);

--
-- Name: ix_ht_sessions_user_started; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_ht_sessions_user_started ON public.ht_sessions USING btree (sh_user_id, started_on DESC) WHERE (is_deleted = false);

--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);

--
-- Name: model_has_permissions_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_permissions_model_id_model_type_index ON public.model_has_permissions USING btree (model_id, model_type);

--
-- Name: model_has_roles_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_roles_model_id_model_type_index ON public.model_has_roles USING btree (model_id, model_type);

--
-- Name: password_resets_email_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX password_resets_email_index ON public.password_resets USING btree (email);

--
-- Name: ux_ht_content_purposes_child; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ux_ht_content_purposes_child ON public.ht_content_purposes USING btree (parent_id, name) WHERE ((parent_id IS NOT NULL) AND (is_deleted = false));

--
-- Name: ux_ht_content_purposes_root; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ux_ht_content_purposes_root ON public.ht_content_purposes USING btree (name) WHERE ((parent_id IS NULL) AND (is_deleted = false));

--
-- Name: ux_ht_messages_session_seq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ux_ht_messages_session_seq ON public.ht_messages USING btree (session_id, seq);

--
-- Name: ux_ht_sessions_uuid; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ux_ht_sessions_uuid ON public.ht_sessions USING btree (session_uuid);

--
-- Name: saathi_beneficiary fk_beneficiary_state; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.saathi_beneficiary
    ADD CONSTRAINT fk_beneficiary_state FOREIGN KEY (state_id) REFERENCES public.saathi_states(state_id) ON DELETE SET NULL;

--
-- Name: ht_content_chunks ht_content_chunks_content_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_content_chunks
    ADD CONSTRAINT ht_content_chunks_content_id_fkey FOREIGN KEY (content_id) REFERENCES public.ht_content_items(content_id) ON DELETE CASCADE;

--
-- Name: ht_content_item_purpose ht_content_item_purpose_content_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_content_item_purpose
    ADD CONSTRAINT ht_content_item_purpose_content_id_fkey FOREIGN KEY (content_id) REFERENCES public.ht_content_items(content_id) ON DELETE CASCADE;

--
-- Name: ht_content_item_purpose ht_content_item_purpose_purpose_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_content_item_purpose
    ADD CONSTRAINT ht_content_item_purpose_purpose_id_fkey FOREIGN KEY (purpose_id) REFERENCES public.ht_content_purposes(purpose_id) ON DELETE CASCADE;

--
-- Name: ht_content_purposes ht_content_purposes_parent_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_content_purposes
    ADD CONSTRAINT ht_content_purposes_parent_id_fkey FOREIGN KEY (parent_id) REFERENCES public.ht_content_purposes(purpose_id) ON DELETE CASCADE;

--
-- Name: ht_messages ht_messages_session_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_messages
    ADD CONSTRAINT ht_messages_session_id_fkey FOREIGN KEY (session_id) REFERENCES public.ht_sessions(session_id) ON DELETE CASCADE;

--
-- Name: ht_qa_pairs ht_qa_pairs_session_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ht_qa_pairs
    ADD CONSTRAINT ht_qa_pairs_session_id_fkey FOREIGN KEY (session_id) REFERENCES public.ht_sessions(session_id) ON DELETE CASCADE;

--
-- Name: model_has_roles model_has_roles_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;

--
-- Name: role_has_permissions role_has_permissions_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;

--
-- Name: users users_state_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_state_id_fkey FOREIGN KEY (state_id) REFERENCES public.saathi_states(state_id);

--
--

\unrestrict 6sipGN29c8pVB5h6q3Rj0v27J9nRp1sC9dlMdWkAO6chPivSaz697wCea6CHC67


-- Default settings rows the admin panel edits (guardrail fallback message and
-- the start pitch). Empty until set in the admin panel.
INSERT INTO public.ht_settings (setting_key, setting_value)
VALUES
    ('guardrail_default_message', '{}'::jsonb),
    ('start_pitch', '{}'::jsonb)
ON CONFLICT (setting_key) DO NOTHING;

COMMIT;
