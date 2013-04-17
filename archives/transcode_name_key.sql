-- Add unique constrain on name/key
CREATE UNIQUE INDEX /*i*/transcode_name_key ON /*_*/transcode (transcode_image_name,transcode_key);
