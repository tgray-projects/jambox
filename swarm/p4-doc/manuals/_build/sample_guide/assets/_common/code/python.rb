require 'open-uri'
DEBUG = true

abort("Come back when you get a mac!") unless RUBY_PLATFORM =~ /darwin/
abort("The actors have come down with a bad case of laryngitis") unless system("which -s say")

class Actor
  attr_reader :voice
  
  def initialize(voice)
    @voice = voice.match(/([A-Z][a-z]+)/)[1]
  end
  
  def say(text)
    system("say -v #{voice} \"#{text}\"")
  end

  def to_s
    voice
  end
end

class Cast
  VOICES_THAT_IRRITATE_ME = ['Albert', 'BadNews', 'Bahh']
  attr_reader :roles
  attr_reader :actors
  
  def initialize
    @roles = {}
    @actos = []
    hire_actors
    cast_role( "NARARATOR")
  end

  def hire_actors
    @actors = []
    Dir.foreach('/System/Library/Speech/Voices') do |file|
      array = file.split('.')
      unless array.empty?
        voice = array.first
        next if VOICES_THAT_IRRITATE_ME.include?(voice)
        @actors << Actor.new(voice)
      end
    end
    abort("We can't find any actors for this performance!") if @actors.empty?
    puts "[HIRED ACTORS] #{actors.join(', ')}" if DEBUG
  end

  def [](role)
    cast_role(role) unless roles[role]
    roles[role]
  end
  
  def cast_role(role)
    index = roles.keys.size % actors.size
    roles[role] = actors[index]
    puts "[CAST] #{actors[index]} as #{role}" if DEBUG
    roles[role]
  end
end

class Director
  def initialize
    @script = open('http://www.textfiles.com/media/SCRIPTS/grail')
  end
    
  def run_lines
    actor_line = ''
    role = nil 
    at_start = false
    @script.each_line do |line|
      next unless at_start || line =~ /Scene/ #skip to first scene
      line.gsub!(/[_\|]/, '')
      line.gsub!(/\s+/, ' ')
      next if line == ' '
      at_start = true
      if (line =~ /\[/ || line =~ /Scene/)
        yield role, actor_line unless actor_line.size.zero?
        yield "NARARATOR", line
        actor_line = ''
        next
      end
      if (line =~ /[A-Z0-9 #]+:/).nil?
        actor_line += line
      else
        yield role, actor_line unless actor_line.size.zero?
        role, actor_line = split_line(line)
      end
    end
    yield role, actor_line unless actor_line.size.zero?
  end

  def split_line(line)
    line.split(':')
  end
end

director = Director.new
cast = Cast.new
director.run_lines {|role, line|
  role = "KING ARTHUR" if role == "ARTHUR" #there is an inconsistency in the script
  puts "[#{role}] #{line}"
  cast[role].say(line)
}
