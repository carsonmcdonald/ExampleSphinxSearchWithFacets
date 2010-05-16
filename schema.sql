-- <badges><row Id="82946" UserId="3718" Name="Teacher" Date="2008-09-15T08:55:03.923" />

CREATE TABLE badge
(
    id INT NOT NULL PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(40) NULL,
    created TIMESTAMP
);

create index badge_user_idx on badge(user_id);

CREATE TABLE badge_type
(
    badge_id CHAR(1) NOT NULL,
    name VARCHAR(40) NOT NULL
);

create index badge_type_idx on badge_type(name);

-- <comments><row Id="1" PostId="35314" Score="1" Text="not sure why this is getting downvoted -- it is correct! Double check it in your compiler if you don't believe him!" CreationDate="2008-09-06T08:07:10.730" UserId="1" />

CREATE TABLE comment
(
    id INT NOT NULL PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    score INT NULL,
    comment_text text NULL,
    created TIMESTAMP
);

CREATE INDEX comment_post_idx ON comment(post_id);

-- <votes><row Id="1" PostId="1" VoteTypeId="2" CreationDate="2008-07-31" />

CREATE TABLE vote
(
    id INT NOT NULL PRIMARY KEY,
    post_id INT NOT NULL,
    vote_type_id INT NOT NULL,
    created TIMESTAMP
);

CREATE INDEX vote_post_idx ON vote(post_id);

-- <users><row Id="-1" Reputation="1" CreationDate="2008-07-31T00:00:00.000" DisplayName="Community" LastAccessDate="2008-08-26T00:16:53.810" WebsiteUrl="http://stackoverflow.com" Location="on the server farm" Age="1" AboutMe="&lt;p&gt;Hi, I'm not really a person.&lt;/p&gt;&#xD;&#xA;&lt;p&gt;I'm a background process that helps keep Stack Overflow clean!&lt;/p&gt;&#xD;&#xA;&lt;p&gt;I do things like&lt;/p&gt;&#xD;&#xA;&lt;ul&gt;&#xD;&#xA;&lt;li&gt;Randomly poke old unanswered questions every hour so they get some attention&lt;/li&gt;&#xD;&#xA;&lt;li&gt;Own community questions and answers so nobody gets unnecessary reputation from them&lt;/li&gt;&#xD;&#xA;&lt;li&gt;Own downvotes on spam/evil posts that get permanently deleted&#xD;&#xA;&lt;/ul&gt; " Views="649" UpVotes="103" DownVotes="13145" />

CREATE TABLE so_user
(
    id INT NOT NULL PRIMARY KEY,
    reputation INT NOT NULL,
    display_name VARCHAR(40) NULL,
    last_access_date TIMESTAMP,
    website_url VARCHAR(256) NULL,
    location VARCHAR(256) NULL,
    age INT NOT NULL,
    about_me text NULL,
    views INT NOT NULL,
    up_votes INT NOT NULL,
    down_votes INT NOT NULL,
    gravatar_hash VARCHAR(256),
    gold_badge_count INT,
    silver_badge_count INT,
    bronze_badge_count INT,
    created TIMESTAMP
);


-- <posts> <row Id="4" PostTypeId="1" AcceptedAnswerId="7" CreationDate="2008-07-31T21:42:52.667" Score="21" ViewCount="3261" Body="" OwnerUserId="8" LastEditorUserId="56555" LastEditorDisplayName="Rich B" LastEditDate="2009-07-25T19:33:10.080" LastActivityDate="2009-07-25T19:33:10.080" Title="" Tags="" AnswerCount="12" CommentCount="7" FavoriteCount="2" />

CREATE TABLE tag
(
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name varchar(256) NOT NULL
);

CREATE INDEX tag_name_idx ON tag(name);

CREATE TABLE post
(
    id INT NOT NULL PRIMARY KEY,
    post_type_id INT NOT NULL,
    accepted_answer_id INT,
    parent_id INT,
    score INT NULL,
    view_count INT NULL,
    body_text text NULL,
    owner_id INT NOT NULL,
    last_editor_user_id INT,
    last_editor_display_name varchar(40),
    last_edit_date TIMESTAMP,
    last_activity_date TIMESTAMP,
    title varchar(256) NOT NULL,
    answer_count INT NOT NULL,
    comment_count INT NOT NULL,
    favorite_count INT NOT NULL,
    created TIMESTAMP
);

CREATE INDEX post_parent_idx ON post(parent_id);

CREATE TABLE post_to_tag
(
    post_id INT NOT NULL,
    tag_id INT NOT NULL
);

CREATE INDEX post_tag_idx ON post_to_tag(post_id,tag_id);

CREATE TABLE vote_type
(
    id INT NOT NULL PRIMARY KEY,
    name VARCHAR(40) NOT NULL
);
 
insert into vote_type(id, name) values(1, 'AcceptedByOriginator');
insert into vote_type(id, name) values(12, 'UpMod');
insert into vote_type(id, name) values(13, 'DownMod');
insert into vote_type(id, name) values(14, 'Offensive');
insert into vote_type(id, name) values(15, 'Favorite');
insert into vote_type(id, name) values(16, 'Close');
insert into vote_type(id, name) values(17, 'Reopen');
insert into vote_type(id, name) values(18, 'BountyStart');
insert into vote_type(id, name) values(19, 'BountyClose');
insert into vote_type(id, name) values(110, 'Deletion');
insert into vote_type(id, name) values(111, 'Undeletion');
insert into vote_type(id, name) values(112, 'Spam');
insert into vote_type(id, name) values(113, 'InformModerator');

create view users_by_location as select count(1) user_count, sum(reputation) rep_sum, lower(location) country from so_user where location is not null group by 3 order by 1 desc;

insert into badge_type(badge_id, name) values('b','Autobiographer');
insert into badge_type(badge_id, name) values('b','Citizen Patrol');
insert into badge_type(badge_id, name) values('b','Cleanup');
insert into badge_type(badge_id, name) values('b','Commentator');
insert into badge_type(badge_id, name) values('b','Critic');
insert into badge_type(badge_id, name) values('b','Disciplined');
insert into badge_type(badge_id, name) values('b','Editor');
insert into badge_type(badge_id, name) values('b','Mortarboard');
insert into badge_type(badge_id, name) values('b','Nice Answer');
insert into badge_type(badge_id, name) values('b','Nice Question');
insert into badge_type(badge_id, name) values('b','Organizer');
insert into badge_type(badge_id, name) values('b','Peer Pressure');
insert into badge_type(badge_id, name) values('b','Popular Question');
insert into badge_type(badge_id, name) values('b','Scholar');
insert into badge_type(badge_id, name) values('b','Self-Learner');
insert into badge_type(badge_id, name) values('b','Student');
insert into badge_type(badge_id, name) values('b','Supporter');
insert into badge_type(badge_id, name) values('b','Teacher');
insert into badge_type(badge_id, name) values('b','Tumbleweed');
insert into badge_type(badge_id, name) values('g','Electorate');
insert into badge_type(badge_id, name) values('g','Famous Question');
insert into badge_type(badge_id, name) values('g','Fanatic');
insert into badge_type(badge_id, name) values('g','Great Answer');
insert into badge_type(badge_id, name) values('g','Great Question');
insert into badge_type(badge_id, name) values('g','Legendary');
insert into badge_type(badge_id, name) values('g','Populist');
insert into badge_type(badge_id, name) values('g','Reversal');
insert into badge_type(badge_id, name) values('g','Stellar Question');
insert into badge_type(badge_id, name) values('s','Enlightened');
insert into badge_type(badge_id, name) values('s','Beta');
insert into badge_type(badge_id, name) values('s','Civic Duty');
insert into badge_type(badge_id, name) values('s','Enthusiast');
insert into badge_type(badge_id, name) values('s','Epic');
insert into badge_type(badge_id, name) values('s','Favorite Question');
insert into badge_type(badge_id, name) values('s','Generalist');
insert into badge_type(badge_id, name) values('s','Good Answer');
insert into badge_type(badge_id, name) values('s','Good Question');
insert into badge_type(badge_id, name) values('s','Guru');
insert into badge_type(badge_id, name) values('s','Necromancer');
insert into badge_type(badge_id, name) values('s','Notable Question');
insert into badge_type(badge_id, name) values('s','Yearling');
insert into badge_type(badge_id, name) values('s','Taxonomist');
insert into badge_type(badge_id, name) values('s','Strunk & White');
insert into badge_type(badge_id, name) values('s','Pundit');
