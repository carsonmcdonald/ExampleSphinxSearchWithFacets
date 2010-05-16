require 'rubygems'
require 'libxml'
require "mysql"
require "pp"

include LibXML

class BadgeCallbacks
  include XML::SaxParser::Callbacks

  @my
  @st

  def initialize(my)
    @my = my
    @st = @my.prepare("insert into badge(id, user_id, name, created) values(?, ?, ?, ?)")
  end

  def on_start_element(element, attributes)
    if element == 'row'
      @st.execute(attributes['Id'], attributes['UserId'], attributes['Name'], attributes['Date'])
    end
  end
end

class CommentCallbacks
  include XML::SaxParser::Callbacks

  @my
  @st

  def initialize(my)
    @my = my
    @st = @my.prepare("insert into comment(id, post_id, user_id, score, comment_text, created) values(?, ?, ?, ?, ?, ?)")
  end

  def on_start_element(element, attributes)
    if element == 'row'
      @st.execute(attributes['Id'], attributes['PostId'], attributes['UserId'] == nil ? -1 : attributes['UserId'], attributes['Score'], attributes['Text'], attributes['CreationDate'])
    end
  end
end

class VoteCallbacks
  include XML::SaxParser::Callbacks

  @my
  @st

  def initialize(my)
    @my = my
    @st = @my.prepare("insert into vote(id, post_id, vote_type_id, created) values(?, ?, ?, ?)")
  end

  def on_start_element(element, attributes)
    if element == 'row'
      @st.execute(attributes['Id'], attributes['PostId'], attributes['VoteTypeId'], attributes['CreationDate'])
    end
  end
end

class UserCallbacks
  include XML::SaxParser::Callbacks

  @my
  @st
  @badge_st

  def initialize(my)
    @my = my
    @st = @my.prepare("insert into so_user(id, reputation, display_name, last_access_date, website_url, location, age, about_me, views, up_votes, down_votes, created, gravatar_hash, gold_badge_count, silver_badge_count, bronze_badge_count) values(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
    @badge_st = @my.prepare("select badge_type.badge_id, count(1) from badge join badge_type on badge_type.name = badge.name where badge.user_id = ? group by badge_type.badge_id")
  end

  def on_start_element(element, attributes)
    if element == 'row'
      gold_count = 0
      silver_count = 0
      bronze_count = 0

      @badge_st.execute(attributes['Id'])
      if @badge_st.num_rows > 0
        0.upto(@badge_st.num_rows) do |index|
          row = @badge_st.fetch
          next if row.nil?
          bronze_count = row[1] if row[0] == 'b'
          silver_count = row[1] if row[0] == 's'
          gold_count = row[1] if row[0] == 'g'
        end
      end

      @st.execute(attributes['Id'], attributes['Reputation'], attributes['DisplayName'], attributes['LastAccessDate'], attributes['WebsiteUrl'], attributes['Location'], attributes['Age'] == nil ? -1 : attributes['Age'], attributes['AboutMe'], attributes['Views'], attributes['UpVotes'], attributes['DownVotes'], attributes['CreationDate'], attributes['EmailHash'], gold_count, silver_count, bronze_count)
    end
  end
end


class PostCallbacks
  include XML::SaxParser::Callbacks

  @my
  @post_st
  @tag_insert_st
  @tag_select_st

  def initialize(my)
    @my = my
    @post_st = @my.prepare("insert into post(id, post_type_id, accepted_answer_id, parent_id, score, view_count, body_text, owner_id, last_editor_user_id, last_editor_display_name, last_edit_date, last_activity_date, title, answer_count, comment_count, favorite_count, created) values(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
    @tag_insert_st = @my.prepare("insert into tag(name) values(?)")
    @tag_select_st = @my.prepare("select id from tag where name = ?")
    @post_ot_tag_insert_st = @my.prepare("insert into post_to_tag(post_id, tag_id) values(?, ?)")
  end

  def on_start_element(element, attributes)
    if element == 'row'
      @post_st.execute(attributes['Id'], attributes['PostTypeId'], attributes['AcceptedAnswerId'], attributes['ParentId'], attributes['Score'], attributes['ViewCount'], attributes['Body'], attributes['OwnerUserId'] == nil ? -1 : attributes['OwnerUserId'], attributes['LastEditorUserId'], attributes['LastEditorDisplayName'], attributes['LastEditDate'], attributes['LastActivityDate'], attributes['Title'] == nil ? '' : attributes['Title'], attributes['AnswerCount'] == nil ? 0 : attributes['AnswerCount'], attributes['CommentCount'] == nil ? 0 : attributes['CommentCount'], attributes['FavoriteCount'] == nil ? 0 : attributes['FavoriteCount'], attributes['CreationDate'])
      post_id = attributes['Id']
      
      tags = attributes['Tags'] == nil ? '' : attributes['Tags']
      tags.scan(/<(.*?)>/).each do |tag_name|
        tag_id = insert_or_find_tag(tag_name[0])
        @post_ot_tag_insert_st.execute(post_id, tag_id)
      end
    end
  end

  def insert_or_find_tag(tag_name)
    @tag_select_st.execute(tag_name)
    if @tag_select_st.num_rows > 0
      @tag_select_st.fetch[0]
    else
      @tag_insert_st.execute(tag_name)
      @tag_insert_st.insert_id
    end
  end
end

if ARGV.size != 5
  puts "Usage load.rb <XML file path> <db host> <db user> <db pass> <db name>"
  exit 1
end

my = Mysql::new(ARGV[1], ARGV[2], ARGV[3], ARGV[4])

puts "Loading badges"

parser = XML::SaxParser.file(ARGV[0] + '/badges.xml')
parser.callbacks = BadgeCallbacks.new(my)
parser.parse

puts "Loading comments"

parser = XML::SaxParser.file(ARGV[0] + '/comments.xml')
parser.callbacks = CommentCallbacks.new(my)
parser.parse

puts "Loading votes"

parser = XML::SaxParser.file(ARGV[0] + '/votes.xml')
parser.callbacks = VoteCallbacks.new(my)
parser.parse

puts "Loading users"

parser = XML::SaxParser.file(ARGV[0] + '/users.xml')
parser.callbacks = UserCallbacks.new(my)
parser.parse

puts "Loading posts"

parser = XML::SaxParser.file(ARGV[0] + '/posts.xml')
parser.callbacks = PostCallbacks.new(my)
parser.parse
