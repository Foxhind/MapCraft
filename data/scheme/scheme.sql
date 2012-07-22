--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = off;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET escape_string_warning = off;

--
-- Name: mapcraft; Type: SCHEMA; Schema: -; Owner: mapcrafter
--

CREATE SCHEMA mapcraft;


ALTER SCHEMA mapcraft OWNER TO mapcrafter;

--
-- Name: SCHEMA mapcraft; Type: COMMENT; Schema: -; Owner: mapcrafter
--

COMMENT ON SCHEMA mapcraft IS 'Mapcraft schema';


SET search_path = mapcraft, pg_catalog;

--
-- Name: class; Type: TYPE; Schema: mapcraft; Owner: mapcrafter
--

CREATE TYPE class AS ENUM (
    'user',
    'info',
    'error'
);


ALTER TYPE mapcraft.class OWNER TO mapcrafter;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: access; Type: TABLE; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

CREATE TABLE access (
    "user" integer,
    pie integer NOT NULL,
    nick character varying(255) NOT NULL,
    role character varying
);


ALTER TABLE mapcraft.access OWNER TO mapcrafter;

--
-- Name: schat; Type: SEQUENCE; Schema: mapcraft; Owner: mapcrafter
--

CREATE SEQUENCE schat
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE mapcraft.schat OWNER TO mapcrafter;

--
-- Name: chat; Type: TABLE; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

CREATE TABLE chat (
    id integer DEFAULT nextval('schat'::regclass) NOT NULL,
    pie integer,
    author integer,
    class class,
    message character varying(1024) NOT NULL,
    "timestamp" timestamp without time zone DEFAULT (now())::timestamp without time zone NOT NULL
);


ALTER TABLE mapcraft.chat OWNER TO mapcrafter;

--
-- Name: chat_members; Type: TABLE; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

CREATE TABLE chat_members (
    pie integer NOT NULL,
    member integer NOT NULL,
    session character varying(32) NOT NULL
);


ALTER TABLE mapcraft.chat_members OWNER TO mapcrafter;

--
-- Name: sclaims; Type: SEQUENCE; Schema: mapcraft; Owner: mapcrafter
--

CREATE SEQUENCE sclaims
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE mapcraft.sclaims OWNER TO mapcrafter;

--
-- Name: claims; Type: TABLE; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

CREATE TABLE claims (
    id integer DEFAULT nextval('sclaims'::regclass) NOT NULL,
    author integer,
    piece integer NOT NULL,
    score integer DEFAULT 0 NOT NULL
);


ALTER TABLE mapcraft.claims OWNER TO mapcrafter;

--
-- Name: spieces; Type: SEQUENCE; Schema: mapcraft; Owner: mapcrafter
--

CREATE SEQUENCE spieces
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE mapcraft.spieces OWNER TO mapcrafter;

--
-- Name: pieces; Type: TABLE; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

CREATE TABLE pieces (
    id integer DEFAULT nextval('spieces'::regclass) NOT NULL,
    owner integer,
    state integer DEFAULT 0 NOT NULL,
    pie integer NOT NULL,
    coordinates text NOT NULL,
    "index" integer NOT NULL
);


ALTER TABLE mapcraft.pieces OWNER TO mapcrafter;

--
-- Name: spieces_comments; Type: SEQUENCE; Schema: mapcraft; Owner: mapcrafter
--

CREATE SEQUENCE spieces_comments
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE mapcraft.spieces_comments OWNER TO mapcrafter;

--
-- Name: pieces_comments; Type: TABLE; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

CREATE TABLE pieces_comments (
    id integer DEFAULT nextval('spieces_comments'::regclass) NOT NULL,
    piece integer NOT NULL,
    author integer,
    text text NOT NULL,
    "timestamp" timestamp without time zone DEFAULT (now())::timestamp without time zone NOT NULL,
    "type" character varying(255) NOT NULL
);


ALTER TABLE mapcraft.pieces_comments OWNER TO mapcrafter;

--
-- Name: spies; Type: SEQUENCE; Schema: mapcraft; Owner: mapcrafter
--

CREATE SEQUENCE spies
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE mapcraft.spies OWNER TO mapcrafter;

--
-- Name: pies; Type: TABLE; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

CREATE TABLE pies (
    id integer DEFAULT nextval('spies'::regclass) NOT NULL,
    name character varying(255) NOT NULL,
    author integer NOT NULL,
    start timestamp without time zone DEFAULT (now())::timestamp without time zone NOT NULL,
    ends timestamp without time zone,
    description text,
    visible boolean DEFAULT true NOT NULL,
    jcenter character varying(255),
    anons integer DEFAULT 0 NOT NULL
);


ALTER TABLE mapcraft.pies OWNER TO mapcrafter;

--
-- Name: susers; Type: SEQUENCE; Schema: mapcraft; Owner: mapcrafter
--

CREATE SEQUENCE susers
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE mapcraft.susers OWNER TO mapcrafter;

--
-- Name: users; Type: TABLE; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

CREATE TABLE users (
    nick character varying(255) NOT NULL,
    id integer DEFAULT nextval('susers'::regclass) NOT NULL,
    color character varying(6) DEFAULT '000000'::character varying NOT NULL
);


ALTER TABLE mapcraft.users OWNER TO mapcrafter;

--
-- Name: votes; Type: TABLE; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

CREATE TABLE votes (
    claim integer NOT NULL,
    author integer NOT NULL,
    value integer DEFAULT 0 NOT NULL
);


ALTER TABLE mapcraft.votes OWNER TO mapcrafter;

--
-- Name: access_pkey; Type: CONSTRAINT; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

ALTER TABLE ONLY access
    ADD CONSTRAINT access_pkey PRIMARY KEY (pie, nick);


--
-- Name: chat_members_pkey; Type: CONSTRAINT; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

ALTER TABLE ONLY chat_members
    ADD CONSTRAINT chat_members_pkey PRIMARY KEY (pie, member, session);


--
-- Name: chat_pkey; Type: CONSTRAINT; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

ALTER TABLE ONLY chat
    ADD CONSTRAINT chat_pkey PRIMARY KEY (id);


--
-- Name: claims_pkey; Type: CONSTRAINT; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

ALTER TABLE ONLY claims
    ADD CONSTRAINT claims_pkey PRIMARY KEY (id);


--
-- Name: pieces_comments_pkey; Type: CONSTRAINT; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

ALTER TABLE ONLY pieces_comments
    ADD CONSTRAINT pieces_comments_pkey PRIMARY KEY (id);


--
-- Name: pieces_pkey; Type: CONSTRAINT; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

ALTER TABLE ONLY pieces
    ADD CONSTRAINT pieces_pkey PRIMARY KEY (id);


--
-- Name: pies_pkey; Type: CONSTRAINT; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

ALTER TABLE ONLY pies
    ADD CONSTRAINT pies_pkey PRIMARY KEY (id);


--
-- Name: users_nick_key; Type: CONSTRAINT; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_nick_key UNIQUE (nick);


--
-- Name: users_pkey; Type: CONSTRAINT; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: votes_pkey; Type: CONSTRAINT; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

ALTER TABLE ONLY votes
    ADD CONSTRAINT votes_pkey PRIMARY KEY (claim, author);


--
-- Name: access_user; Type: INDEX; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

CREATE INDEX access_user ON access USING btree ("user");


--
-- Name: chat_pie_idx; Type: INDEX; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

CREATE INDEX chat_pie_idx ON chat USING btree (pie);


--
-- Name: chat_timestamp_idx; Type: INDEX; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

CREATE INDEX chat_timestamp_idx ON chat USING btree ("timestamp");


--
-- Name: users_id_idx; Type: INDEX; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

CREATE INDEX users_id_idx ON users USING btree (id);


--
-- Name: users_nick_idx; Type: INDEX; Schema: mapcraft; Owner: mapcrafter; Tablespace: 
--

CREATE UNIQUE INDEX users_nick_idx ON users USING btree (nick);


--
-- Name: access_pie_fkey; Type: FK CONSTRAINT; Schema: mapcraft; Owner: mapcrafter
--

ALTER TABLE ONLY access
    ADD CONSTRAINT access_pie_fkey FOREIGN KEY (pie) REFERENCES pies(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: access_user_fkey; Type: FK CONSTRAINT; Schema: mapcraft; Owner: mapcrafter
--

ALTER TABLE ONLY access
    ADD CONSTRAINT access_user_fkey FOREIGN KEY ("user") REFERENCES users(id) MATCH FULL ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: chat_author_fkey; Type: FK CONSTRAINT; Schema: mapcraft; Owner: mapcrafter
--

ALTER TABLE ONLY chat
    ADD CONSTRAINT chat_author_fkey FOREIGN KEY (author) REFERENCES users(id) ON UPDATE CASCADE;


--
-- Name: chat_members_pie_fkey; Type: FK CONSTRAINT; Schema: mapcraft; Owner: mapcrafter
--

ALTER TABLE ONLY chat_members
    ADD CONSTRAINT chat_members_pie_fkey FOREIGN KEY (pie) REFERENCES pies(id);


--
-- Name: chat_members_user_fkey; Type: FK CONSTRAINT; Schema: mapcraft; Owner: mapcrafter
--

ALTER TABLE ONLY chat_members
    ADD CONSTRAINT chat_members_user_fkey FOREIGN KEY (member) REFERENCES users(id);


--
-- Name: chat_pie_fkey; Type: FK CONSTRAINT; Schema: mapcraft; Owner: mapcrafter
--

ALTER TABLE ONLY chat
    ADD CONSTRAINT chat_pie_fkey FOREIGN KEY (pie) REFERENCES pies(id) ON UPDATE CASCADE;


--
-- Name: claims_author_fkey; Type: FK CONSTRAINT; Schema: mapcraft; Owner: mapcrafter
--

ALTER TABLE ONLY claims
    ADD CONSTRAINT claims_author_fkey FOREIGN KEY (author) REFERENCES users(id) ON UPDATE CASCADE;


--
-- Name: claims_piece_fkey; Type: FK CONSTRAINT; Schema: mapcraft; Owner: mapcrafter
--

ALTER TABLE ONLY claims
    ADD CONSTRAINT claims_piece_fkey FOREIGN KEY (piece) REFERENCES pieces(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: pieces_comments_author_fkey; Type: FK CONSTRAINT; Schema: mapcraft; Owner: mapcrafter
--

ALTER TABLE ONLY pieces_comments
    ADD CONSTRAINT pieces_comments_author_fkey FOREIGN KEY (author) REFERENCES users(id) ON UPDATE CASCADE;


--
-- Name: pieces_comments_piece_fkey; Type: FK CONSTRAINT; Schema: mapcraft; Owner: mapcrafter
--

ALTER TABLE ONLY pieces_comments
    ADD CONSTRAINT pieces_comments_piece_fkey FOREIGN KEY (piece) REFERENCES pieces(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: pieces_owner_fkey; Type: FK CONSTRAINT; Schema: mapcraft; Owner: mapcrafter
--

ALTER TABLE ONLY pieces
    ADD CONSTRAINT pieces_owner_fkey FOREIGN KEY (owner) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: pieces_pie_fkey; Type: FK CONSTRAINT; Schema: mapcraft; Owner: mapcrafter
--

ALTER TABLE ONLY pieces
    ADD CONSTRAINT pieces_pie_fkey FOREIGN KEY (pie) REFERENCES pies(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: pies_author_fkey; Type: FK CONSTRAINT; Schema: mapcraft; Owner: mapcrafter
--

ALTER TABLE ONLY pies
    ADD CONSTRAINT pies_author_fkey FOREIGN KEY (author) REFERENCES users(id) ON UPDATE CASCADE;


--
-- Name: votes_author_fkey; Type: FK CONSTRAINT; Schema: mapcraft; Owner: mapcrafter
--

ALTER TABLE ONLY votes
    ADD CONSTRAINT votes_author_fkey FOREIGN KEY (author) REFERENCES users(id) ON UPDATE CASCADE;


--
-- Name: votes_claim_fkey; Type: FK CONSTRAINT; Schema: mapcraft; Owner: mapcrafter
--

ALTER TABLE ONLY votes
    ADD CONSTRAINT votes_claim_fkey FOREIGN KEY (claim) REFERENCES claims(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: mapcraft; Type: ACL; Schema: -; Owner: mapcrafter
--

REVOKE ALL ON SCHEMA mapcraft FROM PUBLIC;
REVOKE ALL ON SCHEMA mapcraft FROM mapcrafter;
GRANT ALL ON SCHEMA mapcraft TO mapcrafter;
GRANT ALL ON SCHEMA mapcraft TO PUBLIC;


--
-- PostgreSQL database dump complete
--

